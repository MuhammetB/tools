<?php

namespace App\Trait;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasFilter
{

    public function scopeExportFilter(Builder $query)
    {
        if (request()->trashed === true || request()->trashed === 'true') {
            $query->onlyTrashed();
        }
        if (request()->checkBackendElements !== true && request()->checkBackendElements !== 'true') {
            $query->whereIn('id', explode(",", request()->checked));
        }
    }

    public function scopeSearch(Builder $query, $value)
    {
        foreach ($this->searchable as $column) {
            if (Str::contains($column, '.')) {
                $searchable_column = Str::afterLast($column, ".");
                $relationName = Str::beforeLast($column, ".");
                $query->when($value, fn($subq) => $subq->orWhereHas($relationName, function (Builder $query) use ($searchable_column, $value) {
                    $query->whereLike($searchable_column, $value);
                }));
            } elseif (Str::contains($column, ':')) {
//                foreach (explode(',', Str::after($column, ':')) as $col) {
//                    $query->when($value, fn($q) => $q->orWhereLike($col, $value));
//                }
                $columns = implode(", ' ', ", explode(',', Str::after($column, ':')));
                $query->orWhere(DB::raw("CONCAT($columns)"), 'LIKE', "%{$value}%");
            } elseif (Str::contains($column, '|exact')) {
                $query->when($value, fn($q) => $q->orWhere(Str::before($column, '|exact'), $value));
            } else {
                $query->when($value, fn($q) => $q->orWhereLike($column, $value));
            }
        }
    }

    public function scopeFilterByColumn(Builder $query)
    {
        foreach ($this->searchable as $column) {
            if (Str::contains($column, ':')) {
                $search = request()["search_by_" . Str::before($column, ':')];
                $key = "search_by_" . Str::before($column, ":");
                if ($search) {
//                    foreach (explode(',', Str::after($column, ':')) as $col) {
//                        $query->when(request()->$key, fn($q) => $q->orWhereLike($col, $search));
//                    }
                    $columns = implode(", ' ', ", explode(',', Str::after($column, ':')));
                    $query->where(DB::raw("CONCAT($columns)"), 'LIKE', "%{$search}%");
                }
            } elseif (Str::contains($column, '.')) {
                $key = "search_by_" . Str::beforeLast($column, ".");
                $query->when(request()->$key, fn($q) => $q->whereHas(Str::beforeLast($column, "."), function (Builder $query) use ($column, $key) {
                    $query->where('id', request()->$key);
                }));
            } elseif (Str::contains($column, '|exact')) {
                $column_name = Str::before($column, '|exact');
                $search = request()["search_by_$column_name"];
                $query->when($search, fn($q) => $q->where(Str::before($column_name, '|exact'), $search));
            } else {
                $key = "search_by_$column";
                $query->when(request()->$key, fn($q) => $q->whereLike($column, request()->$key));
            }
        }
    }

    public function scopeFilterByDate(Builder $query, $column, $value)
    {
        if ($value === 'today') {
            $query->whereDate($column, '=', date("Y-m-d"));
        } elseif ($value === 'yesterday') {
            $query->whereDate($column, '=', date("Y-m-d", strtotime("-1 day")));
        } elseif ($value === 'this_month') {
            $query->whereMonth($column, date("m"));
            $query->whereYear($column, date("Y"));
        } else {
            $query->whereYear($column, $value);
        }
    }

    public function scopeFilter(Builder $query): void
    {
        $query->when(request()->search, fn($q) => $q->search(request()->search))
            ->when(request()->updated_at, fn($q) => $q->filterByDate('updated_at', request()->updated_at))
            ->when(request()->deleted_at, fn($q) => $q->filterByDate('deleted_at', request()->deleted_at))
            ->when(collect(request()->all())->search(fn($value, $key) => Str::startsWith($key, 'search_by_')), fn($q) => $q->filterByColumn())
            ->when(request()->trashed, fn($q) => $q->onlyTrashed())
            ->when(request()->with_trashed && !request()->trashed, fn($q) => $q->withTrashed())
            ->when(request()->owned, fn($q) => $q->owned());

    }


}
