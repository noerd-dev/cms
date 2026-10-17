@php
    use Illuminate\Foundation\Vite;
    use Noerd\Cms\Support\AssetManifest;

    // Written by `npm run dev` inside app-modules/cms — while the Vite dev
    // server runs, the editor is served from there instead of the asset route.
    $hotFile = base_path('public/vendor/cms/hot');
@endphp

@if (file_exists($hotFile))
    {{ (clone app(Vite::class))->useHotFile($hotFile)->withEntryPoints([AssetManifest::SCRIPT_ENTRY]) }}
@else
    @foreach (AssetManifest::styleFiles() as $styleFile)
        <link rel="stylesheet" href="{{ route('cms.asset', ['file' => $styleFile]) }}">
    @endforeach
    <script type="module" src="{{ route('cms.asset', ['file' => AssetManifest::scriptFile()]) }}"></script>
@endif
