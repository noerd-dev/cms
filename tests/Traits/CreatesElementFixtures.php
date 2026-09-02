<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Illuminate\Support\Facades\File;

/**
 * Element discovery (FieldHelper, HandlesPageElements) globs
 * `app-modules/{star}/resources/views/components/elements/`. The CMS ships no
 * elements of its own, so its tests used to borrow the ones from the optional
 * website module — an invisible cross-module dependency. Instead every test that
 * needs a real element writes its own throwaway fixture into the CMS module's
 * (otherwise empty) elements folder and removes it again afterwards.
 */
trait CreatesElementFixtures
{
    /** @var array<int, string> */
    private array $zzElementFixtureFiles = [];

    private bool $zzElementFixtureDirCreated = false;

    /**
     * The element key of the plain single-field fixture element.
     */
    protected function zzTextElementKey(): string
    {
        return 'zz_fixture_text';
    }

    /**
     * The element key of the fixture element declaring an `element-collection`
     * field named `items`.
     */
    protected function zzCollectionElementKey(): string
    {
        return 'zz_fixture_collection';
    }

    protected function elementFixtureDir(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/components/elements';
    }

    /**
     * Write both fixture elements (blade + yml) so element discovery finds them.
     */
    protected function createElementFixtures(): void
    {
        $dir = $this->elementFixtureDir();

        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir);
            $this->zzElementFixtureDirCreated = true;
        }

        $this->writeElementFixture('zz-fixture-text', <<<'YAML'
            title: 'Zz Fixture Text'
            description: 'Throwaway test element'
            group: 'Zz Test'
            fields:
              - name: detailData.text
                label: Text
                type: translatableRichText
                colspan: 12
            YAML);

        $this->writeElementFixture('zz-fixture-collection', <<<'YAML'
            title: 'Zz Fixture Collection'
            description: 'Throwaway test element with an element-collection field'
            group: 'Zz Test'
            fields:
              - name: detailData.headline
                label: Headline
                type: translatableText
                colspan: 12
              - name: detailData.items
                label: Items
                type: element-collection
                colspan: 12
                fields:
                  - name: image
                    label: Image
                    type: image
                    colspan: 12
            YAML);
    }

    /**
     * Remove every fixture file this test wrote (and the folder, when the test
     * created it), so the module tree is left exactly as it was found.
     */
    protected function removeElementFixtures(): void
    {
        foreach ($this->zzElementFixtureFiles as $file) {
            File::delete($file);
        }

        $this->zzElementFixtureFiles = [];

        if ($this->zzElementFixtureDirCreated) {
            File::deleteDirectory($this->elementFixtureDir());
            $this->zzElementFixtureDirCreated = false;
        }
    }

    private function writeElementFixture(string $fileBase, string $yaml): void
    {
        $dir = $this->elementFixtureDir();

        $blade = $dir . '/' . $fileBase . '.blade.php';
        $definition = $dir . '/' . $fileBase . '.yml';

        File::put($blade, "<div>{{ \$element->text ?? '' }}</div>\n");
        File::put($definition, $yaml . "\n");

        $this->zzElementFixtureFiles[] = $blade;
        $this->zzElementFixtureFiles[] = $definition;
    }
}
