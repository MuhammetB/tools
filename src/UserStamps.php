<?php

namespace App\Trait;

use App\Models\User;
use App\Observers\ArchiveObserver;
use App\Observers\StampObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait UserStamps
{

    public static function bootUserStamps()
    {
        if (Auth::check()) {
            static::observe(new StampObserver());
        }
    }

    public function scopeOwned(Builder $query): void
    {
        $query->where('created_by', Auth::id());
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updated_by_user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getModelStampsAttribute()
    {
        return ["created_by" => "{$this->created_by_user?->id}-{$this->created_by_user?->name}",
            "updated_by" => "{$this->updated_by_user?->id}-{$this->updated_by_user?->name}",
            "created_at" => $this->created_at?->format("Y-m-d (h:i)A"),
            "created_from" => $this->created_at?->diffForHumans(),
            "updated_at" => $this->updated_at?->format("Y-m-d (h:i)A"),
            "updated_from" => $this->updated_at?->diffForHumans(),
            "deleted_at" => $this->deleted_at?->format("Y-m-d (h:i)A"),
            "deleted_from" => $this->deleted_at?->diffForHumans(),];
    }
}
