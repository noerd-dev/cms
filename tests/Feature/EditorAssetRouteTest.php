<?php

declare(strict_types=1);

use Noerd\Cms\Support\AssetManifest;

uses(Noerd\Cms\Tests\TestCase::class);

it('serves the files of the editor bundle listed in the manifest', function (): void {
    $script = AssetManifest::scriptFile();

    $this->get(route('cms.asset', ['file' => $script]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    foreach (AssetManifest::styleFiles() as $style) {
        $this->get(route('cms.asset', ['file' => $style]))->assertOk();
    }
});

it('serves nothing the manifest does not list', function (string $file): void {
    $this->get('/cms-assets/' . $file)->assertNotFound();
})->with([
    'manifest itself' => 'manifest.json',
    'unknown file' => 'cms-editor-evil.js',
]);
