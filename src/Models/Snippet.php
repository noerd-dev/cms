<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Snippet extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_1',
        'checkout_2',
        'checkout_3',
        'checkout_4',
        'checkout_5',
        'checkout_6',
        'checkout_7',
        'checkout_8',
        'checkout_9',
        'checkout_10',
    ];
}
