<?php

namespace OmarSeyam\Cacheable\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class CustomPaginatorAction
{
    /**
     * Create a new class instance.
     */
    public function handle(Collection|SupportCollection|LengthAwarePaginator $data, int $items_count, int $current_page, int $perPage)
    {
        $paginator = new LengthAwarePaginator(
            $data,
            $items_count,
            $perPage,
            $current_page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginator;
    }
}
