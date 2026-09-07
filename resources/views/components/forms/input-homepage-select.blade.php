@props([
    'field' => [],
])

@php
    /*
     | The homepage picker is a plain select whose options (the tenant's pages)
     | come from the field type resolver — rendered through the active theme.
     */
    $theme = \Noerd\Support\ThemeContext::current() ?? 'default';
@endphp

@include(\Noerd\Support\ThemeElementResolver::resolveElement('input-select', $theme) ?? 'themes::default.input-select', ['field' => $field])
