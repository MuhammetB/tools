<?php

namespace App\Trait;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasOrder
{
    public function scopeOrder(Builder $query): void
    {
        $orderBy = request()->order_by ?? request()->order_by_desc;
        if ($orderBy) {
            $orderType = request()->order_by_desc ? 'desc' : 'asc';
            foreach ($this->ordarelable as $column) {
                if (Str::contains($column, '.') && $orderBy === Str::beforeLast($column, '.')) {
                    $query->with(Str::beforeLast($column, '.'))->orderBy($column, $orderType);
                } elseif (Str::contains($column, ':') && $orderBy === Str::before($column, ':')) {
                    foreach (explode(',', Str::after($column, ':')) as $dbColumn) {
                        $query->orderBy($dbColumn, $orderType);
                    }
                } elseif ($orderBy === $column) {
                    $query->orderBy($orderBy, $orderType);
                }
            }
        }
    }
}
