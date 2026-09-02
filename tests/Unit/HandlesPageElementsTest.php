<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Cms\Services\PageElementService;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Tests\TestCase::class);
uses(CreatesElementFixtures::class);

beforeEach(function (): void {
    $this->createElementFixtures();
    $this->service = new PageElementService();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

describe('elementDefinitionExists', function (): void {
    it('confirms an element that ships both a blade and a yml file', function (): void {
        expect($this->service->elementDefinitionExists('elements.zz-fixture-text'))->toBeTrue();
    });

    it('refuses an element key that tries to escape the elements folder', function (): void {
        // element_key is user-supplied through the page editor. A traversal
        // attempt must never resolve to a file outside the elements folders.
        foreach ([
            '../../../../composer',
            'elements.../../../../composer',
            '../../../../../../etc/passwd',
        ] as $traversal) {
            expect($this->service->elementDefinitionExists($traversal))->toBeFalse();
        }
    });

    it('refuses an element that has a blade file but no yml definition', function (): void {
        File::delete($this->elementFixtureDir() . '/zz-fixture-text.yml');

        expect($this->service->elementDefinitionExists('elements.zz-fixture-text'))->toBeFalse();
    });
});

describe('getComponentMapping', function (): void {
    it('maps a discovered element file to its snake_case element key', function (): void {
        expect($this->service->getComponentMapping())
            ->toHaveKey($this->zzTextElementKey(), 'elements.zz-fixture-text');
    });

    it('never maps a traversing element key', function (): void {
        expect($this->service->getComponentMapping())->not->toHaveKey('../../../../composer');
    });

    it('adds the elements of the configured custom path without duplicating a shadowed name', function (): void {
        // noerd_cms.page_elements_path (CMS_PAGE_ELEMENTS_PATH) is the supported
        // hook for a project-owned element folder: its files join the mapping,
        // and a file whose basename also exists in a module occupies the same
        // single key instead of producing a second entry.
        $before = $this->service->getComponentMapping();

        $custom = 'storage/framework/testing/zz-cms-custom-elements';
        File::ensureDirectoryExists(base_path($custom));
        File::put(base_path($custom . '/zz-fixture-text.blade.php'), "<div>custom</div>\n");
        File::put(base_path($custom . '/zz-only-here.blade.php'), "<div>only here</div>\n");

        config(['noerd_cms.page_elements_path' => $custom]);

        try {
            $mapping = $this->service->getComponentMapping();

            expect($mapping)->toHaveKey('zz_only_here', 'elements.zz-only-here')
                ->and($mapping)->toHaveKey($this->zzTextElementKey(), 'elements.zz-fixture-text')
                ->and(count($mapping))->toBe(count($before) + 1);
        } finally {
            File::deleteDirectory(base_path($custom));
        }
    });
});
