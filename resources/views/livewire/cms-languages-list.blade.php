<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'cms-languages-list';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'cms-language-detail',
            source: self::COMPONENT,
            arguments: ['cmsLanguageId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = CmsLanguage::paginate(self::PAGINATION);

        $tableConfig = $this->getTableConfig();

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        if ((int) request()->cmsLanguageId) {
            $this->tableAction(request()->cmsLanguageId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>
