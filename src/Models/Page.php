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
        return $this->hasMany(ElementPage::class)->orderBy('sort');
    }

    public function collection()
    {
        return $this->hasOne(CollectionRow::class, 'page_id');
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'page_collection')
            ->withPivot('sort_order', 'tenant_id')
            ->withTimestamps()
            ->orderBy('page_collection.sort_order');
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
