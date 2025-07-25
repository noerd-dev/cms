<?php

namespace Noerd\Cms\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Models\Text;
use Noerd\Noerd\Traits\NoerdModelTrait;

class ContentComponent extends Component
{
    use NoerdModelTrait;

    public const COMPONENT = 'contents::livewire.content-component';

    public array $content;

    public function mount(): void
    {
        $this->content = Text::where('tenant_id', Auth::user()->selected_tenant_id)->first()->toArray();
    }

    public function store(): void
    {
        Text::updateOrCreate(['tenant_id' => Auth::user()->selected_tenant_id], $this->content);

        $this->showSuccessIndicator = true;
    }

    #[On('startText')]
    public function startText($value): void
    {
        $this->content['start'] = $value;
    }

    #[On('checkoutText')]
    public function checkoutText($value): void
    {
        $this->content['checkout'] = $value;
    }

    #[On('bottomText')]
    public function bottomText($value): void
    {
        $this->content['bottom_text'] = $value;
    }

    #[On('imprintText')]
    public function imprintText($value): void
    {
        $this->content['imprint'] = $value;
    }

    #[On('agbText')]
    public function agbText($value): void
    {
        $this->content['agb'] = $value;
    }

    #[On('versandText')]
    public function versandText($value): void
    {
        $this->content['versand'] = $value;
    }

    #[On('privacyText')]
    public function privacyText($value): void
    {
        $this->content['privacy'] = $value;
    }

    public function render()
    {
        return view(self::COMPONENT);
    }
}
