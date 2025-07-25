<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function elements()
    {
        return $this->belongsToMany(Element::class)->withPivot('id', 'sort', 'data')->orderBy('sort');
    }

    public function collection()
    {
        return $this->hasOne(Collection::class);
    }

    protected static function newFactory()
    {
        return \Noerd\Cms\Database\Factories\PageFactory::new();
    }

    /*
    public function toArray()
    {
        $data = parent::toArray();

        // TODO: auto decode JSON fields
        $data['name'] = json_decode($data['name'], true) ?? $data['name'];

        return $data;
    }
    */
}
