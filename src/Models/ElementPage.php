<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElementPage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'element_page';

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function element()
    {
        return $this->belongsTo(Element::class);
    }

    public function getElementKeyAttribute()
    {
        // If we have a related element, use its element_key
        if ($this->element && $this->element->element_key) {
            return $this->element->element_key;
        }

        // Fallback: try to get element_key from attributes (in case it's directly stored)
        if (isset($this->attributes['element_key'])) {
            return $this->attributes['element_key'];
        }

        // Last fallback: default element type (to prevent view errors)
        return 'text_block_1_column';
    }
}
