<?php

namespace App\Trait;

use App\Models\Archive;
use App\Observers\ArchiveObserver;

trait HasArchive
{
    public function archives()
    {
        $this->morphMany(Archive::class, 'model');
    }

    public static function bootHasArchive()
    {
        static::observe(new ArchiveObserver());
    }


}
