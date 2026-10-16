<?php

declare(strict_types=1);

namespace Noerd\Cms\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Noerd\Models\SetupLanguage;
use Noerd\Services\ColumnFilterParser;

/**
 * The list of records that extend a content (ExtendsContent): the `name`
 * column lives on the content, so the list searches and sorts it here and
 * shows it in the selected language. Use it in a NoerdList component:
 *
 *     public function listData(): array
 *     {
 *         return $this->buildList($this->contentRecordRows());
 *     }
 */
trait ListsContentRecords
{
    protected function contentRecordRows(): LengthAwarePaginator
    {
        // listQuery() applies the column filters and the sort; the search runs
        // below, because one of its columns is not a column of the table.
        $search = (string) $this->search;
        $this->search = '';
        $query = $this->listQuery($this->listModel);
        $this->search = $search;

        $language = SetupLanguage::selectedCode();
        $table = (new $this->listModel())->getTable();

        if ($search !== '') {
            $config = $this->getListConfig();
            $columns = ! empty($config['searchableColumns'])
                ? $config['searchableColumns']
                : collect($config['columns'] ?? [])->pluck('field')->filter()->all();

            $query->where(function ($query) use ($search, $columns, $table): void {
                $query->whereContentNameContains($search);

                foreach ($columns as $column) {
                    if (is_string($column) && $column !== 'name' && Schema::hasColumn($table, $column)) {
                        ColumnFilterParser::applyLikeContains($query, $column, $search, 'or');
                    }
                }
            });
        }

        if ($this->sortField === 'name') {
            $query->reorder()->orderByContentName($language, $this->sortAsc ? 'asc' : 'desc');
        }

        $rows = $query->with('content')->paginate($this->perPage);

        foreach ($rows->items() as $row) {
            $row->name = $row->content?->localizedName($language) ?? '';
        }

        return $rows;
    }
}
