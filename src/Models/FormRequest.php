<?php

namespace Noerd\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Cms\Database\Factories\FormRequestFactory;
use Noerd\Traits\BelongsToTenant;

class FormRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'form_requests';

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_id', 'id');
    }

    protected static function newFactory(): FormRequestFactory
    {
        return FormRequestFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
