<?php

namespace Noerd\Website\Components;

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

abstract class BaseWebsiteElement extends Component
{
    use NoerdElement;

    public object $element;

    public function mount($data = [], array $collections = []): void
    {
        $this->element = is_object($data) ? $data : (object) $data;
        $this->collections = $collections;

        // Call initialize method if it exists
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    /**
     * Override this method in child components to initialize specific data
     */
    protected function initialize(): void
    {
        // Override in child components
    }
}
