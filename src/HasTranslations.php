<?php

namespace App\Trait;

use App\Models\User;
use App\Observers\TranslationObserver;
use Illuminate\Database\Eloquent\Builder;

trait HasTranslations
{
    public function initializeHasTranslations()
    {
        foreach ($this->getMultiLanguageColumnsWithExt() as $multi_language_column_ext) {
            $this->mergeCasts([$multi_language_column_ext => 'array']);
        }
    }

    public static function bootHasTranslations()
    {
        static::observe(new TranslationObserver());
    }

    function getMultiLanguageColumnsWithExt()
    {
        $array = [];
        foreach ($this->getMultiLanguageColumns() as $multi_language_column) {
            $array[] = "{$multi_language_column}_" . config('translation.column_ext');
        }
        return $array;
    }

    function getMultiLanguageColumns()
    {
        $translatable = config('translation.translatable_multi_language_columns');
        return $this->$translatable ?? [];
    }


    public function getAttribute($key)
    {
        $lang = app()->getLocale();
        foreach ($this->getMultiLanguageColumns() ?? [] as $column) {
            if ("translated_$column" === $key) {
                $column_lang = $column . "_" . config('translation.column_ext');
                return $this->$column_lang[$lang] ?? parent::getAttribute($column);
            }
        }
        return parent::getAttribute($key);
    }

    public function getModelTranslationsAttribute()
    {
        return array_reduce($this->getMultiLanguageColumnsWithExt(), function ($translations, $column) {
            $translations[$column] = $this->$column;
            return $translations;
        }, []);
    }

}
