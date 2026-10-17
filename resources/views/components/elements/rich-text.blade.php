<?php

use Livewire\Component;
use Noerd\Support\HtmlSanitizer;

/*
 | Element "rich_text": the running text the document editor stores between
 | two elements. Self-contained (no website trait), so every frontend renders
 | it; the HTML is sanitized on the way out as well as on save.
 */
new class extends Component {
    public $element;

    public function mount($data = [], array $collections = []): void
    {
        $this->element = is_object($data) ? $data : (object) $data;
    }
}; ?>

<div class="rich-text">
    {!! HtmlSanitizer::sanitize(is_string($element->text ?? null) ? $element->text : '') !!}
</div>
