<?php

namespace App\Trait;

use Illuminate\Database\Eloquent\Builder;

trait HasMultiActions
{

    function multiDestroyRestoreMsg($request,$model){
        $action = $request->action === 'destroy' ? 'destroyed' : 'restored';
        $selected = ($request->allElements==='true' ? 'All ' : 'Selected ') . ($request->action === 'restore' ? 'deleted ' : '') . $model;
        return "$selected have been $action successfully";
    }

    function scopeMultiDestroyRestore(Builder $query)
    {
        if (request()->action === 'destroy') {
            $query = request()->allElements === 'true' ? $query : $query->whereIn('id', explode(",", request()->elements));
            $query->delete();
        } else {
            $query = request()->allElements === 'true' ? $query->onlyTrashed() : $query->onlyTrashed()->whereIn('id', explode(",", request()->elements));
            $query->restore();
        }
    }

}
