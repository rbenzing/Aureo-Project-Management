<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SearchIndex;
use App\Services\LoggerService;

/**
 * Searchable
 *
 * Keeps the searchable_index in sync with a model's writes. Mixed into entity
 * models via `use Searchable`. BaseModel::create/update/delete invoke the
 * afterSave/afterDelete hooks on success.
 *
 * Consuming classes MUST implement:
 *   - searchEntityType(): string                 e.g. 'company'
 *   - toSearchIndexRow(int $id): ?array          [title, snippet, ?projectId, searchBlob] or null
 *
 * Indexing is best-effort: the user's write already succeeded, so a failure here
 * is logged and swallowed — it must never break the save.
 */
trait Searchable
{
    private ?SearchIndex $searchIndex = null;

    /** Inject a SearchIndex (used by tests). */
    public function setSearchIndex(SearchIndex $index): void
    {
        $this->searchIndex = $index;
    }

    abstract public function searchEntityType(): string;

    /** @return array{0:string,1:string,2:?int,3:string}|null */
    abstract public function toSearchIndexRow(int $id): ?array;

    public function afterSave(int $id): void
    {
        try {
            $row = $this->toSearchIndexRow($id);
            if ($row === null) {
                return;
            }
            [$title, $snippet, $projectId, $searchBlob] = $row;
            $this->searchIndexInstance()->upsert(
                $this->searchEntityType(),
                $id,
                $title,
                $snippet,
                $projectId,
                $searchBlob,
            );
        } catch (\Throwable $e) {
            LoggerService::getInstance()->exception($e, [
                'context' => 'Searchable::afterSave',
                'entity' => $this->searchEntityType(),
                'id' => $id,
            ]);
        }
    }

    public function afterDelete(int $id): void
    {
        try {
            $this->searchIndexInstance()->markDeleted($this->searchEntityType(), $id);
        } catch (\Throwable $e) {
            LoggerService::getInstance()->exception($e, [
                'context' => 'Searchable::afterDelete',
                'entity' => $this->searchEntityType(),
                'id' => $id,
            ]);
        }
    }

    private function searchIndexInstance(): SearchIndex
    {
        if ($this->searchIndex === null) {
            $this->searchIndex = new SearchIndex();
        }

        return $this->searchIndex;
    }
}
