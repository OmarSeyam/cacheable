<?php

namespace OmarSeyam\Cacheable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OmarSeyam\Cacheable\Actions\CustomPaginatorAction;

trait Cacheable
{
    public static string $class = __CLASS__;

    public static string $current_page;

    public static function bring(?callable $callback = null, int $perPage = 10, ?string $cache_name = null, ?int $ttl = null, string $search_name = 'title', ?callable $filter = null, bool $is_paginate = false, bool $is_public = false, bool $is_connected = true)
    {
        $query = self::_query($callback);
        if ($is_paginate) {
            self::$current_page = request('page', 1);
            $count = self::getCount($query, $cache_name, $ttl, $is_public, $search_name, $filter, $is_connected);
            if (self::$current_page > ceil($count / $perPage)) {
                self::$current_page = 1;
            }
        }

        $cache_name = self::getCacheName($cache_name, $is_paginate, $is_public);
        ${$cache_name} = self::getFilterdData($search_name, $filter, $cache_name, $is_paginate, $query, $perPage, $is_connected) ??
            unserialize(Cache::remember($cache_name, $ttl ?? now()->addHour(), function () use ($query, $is_paginate, $perPage) {
                return serialize($is_paginate ? $query->skip((self::$current_page - 1) * $perPage)->take($perPage)->get()
                    : $query->get());
            }));
        if (! $is_paginate) {
            return ${$cache_name};
        }

        $paginator = new CustomPaginatorAction;

        return $paginator->handle(${$cache_name}, $count, self::$current_page, $perPage);
    }

    protected static function _query(?callable $callback)
    {
        $query = self::$class::query();
        if ($callback) {
            $callback($query);
        }

        return $query;
    }

    protected static function getClassName(): string
    {
        $obj = new self::$class;
        if ($obj->table) {
            return $obj->table;
        }

        $class_name = lcfirst(substr(self::$class, strripos(self::$class, '\\') + 1));
        $class_name = str_ends_with($class_name, 'y') ? substr($class_name, 0, -1) . 'ies' : $class_name . 's';

        return $class_name;
    }

    protected static function getCount(Builder|QueryBuilder $query, ?string $cache_name, ?int $ttl, bool $is_public, string $search_name, ?callable $filter, bool $is_connected)
    {
        $cache_name ??= self::getClassName();
        $cache_name .= '_count';
        if (! $is_public) {
            $cache_name .= '#' . Auth::id();
        }
        self::deletingCacheController($cache_name, $is_connected);

        $name = trim(request($search_name, null));
        if (! $name && ! $filter) {
            return
                Cache::remember($cache_name, $ttl ?? now()->addHour(), function () use ($query) {
                    return DB::query()
                        ->fromSub($query, 'sub')
                        ->count();
                });
        }
        if ($name) {
            $query->whereRaw("LOWER({$search_name}) LIKE ?", ['%' . mb_strtolower($name) . '%']);
        }
        if ($filter) {
            $filter($query);
        }

        return DB::query()
            ->fromSub($query, 'sub')
            ->count();
    }

    protected static function getCacheName(?string $cache_name, bool $is_paginate, bool $is_public): string
    {
        $cache_name ??= self::getClassName();
        if ($is_paginate) {
            $cache_name = $cache_name . '_' . str(self::$current_page);
        }
        if ($is_public) {
            return $cache_name;
        }

        return $cache_name . '#' . Auth::id();
    }

    protected static function getFilterdData(string $search_name, ?callable $filter, string $cache_name, bool $is_paginate, Builder|QueryBuilder $query, int $perPage, bool $is_connected)
    {
        $name = trim(request($search_name, null));
        if ($name || $filter) {
            if ($is_paginate) {
                return $query->skip((self::$current_page - 1) * $perPage)->take($perPage)->get();
            }
            if ($name) {
                $query->whereRaw("LOWER({$search_name}) LIKE ?", ['%' . mb_strtolower($name) . '%']);
            }
            if ($filter) {
                $filter($query);
            }
            return $query->get();
        }

        self::deletingCacheController($cache_name, $is_connected);

        return null;
    }

    protected static function deletingCacheController(string $cache_name, bool $is_connected)
    {
        if (! $is_connected) return;
        $all_cache = Cache::get('deleting_cache#' . Auth::id(), []);
        $class_caches = $all_cache[self::$class] ?? [];

        if (\in_array($cache_name, $class_caches)) {
            return;
        }
        $class_caches[] = $cache_name;
        $all_cache[self::$class] = $class_caches;
        Cache::put('deleting_cache#' . Auth::id(), $all_cache, now()->addDay());
    }

    public static function deletingCache(?string $user_id = null, ...$relations)
    {
        $all_caches = Cache::get('deleting_cache#' . (Auth::id() ?? $user_id), []);
        $relations[] = self::$class;

        foreach ($relations as $rel) {
            if (empty($all_caches[$rel])) {
                continue;
            }
            foreach ($all_caches[$rel] as $cache) {
                Cache::forget($cache);
            }
            unset($all_caches[$rel]);
        }
        Cache::put('deleting_cache#' . (Auth::id() ?? $user_id), $all_caches, now()->addDay());
    }
}
