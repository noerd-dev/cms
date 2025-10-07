<?php

namespace Noerd\Website\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function entries(): array
    {
        $pages = $this->hasMany(Page::class)->get();
        $entries = [];
        foreach ($pages as $page) {
            $data = json_decode($page->data, true);
            $transformedValue = [];
            $transformedValue['id'] = $page->id;
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $transformedValue[$key] = $value[session('selectedLanguage', 'de')] ?? null;
                } else {
                    $transformedValue[$key] = $value;
                }
            }
            $entries[] = $transformedValue;

        }

        return $entries;
    }
}
