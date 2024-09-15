<?php

namespace App\Filament\Tables\Actions;

use Closure;
use Filament\Actions\Concerns\CanReplicateRecords;
use Filament\Actions\Contracts\ReplicatesRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReplicateBulkAction extends BulkAction implements ReplicatesRecords
{
    use CanReplicateRecords;

    protected ?Closure $afterReplicaSaved = null;

    protected array $relationshipsToReplicate = [];

    public static function getDefaultName(): ?string
    {
        return 'replicate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Replicate Selected'));

        $this->modalHeading(fn (): string => __('Replicate selected :label', ['label' => $this->getPluralModelLabel()]));

        $this->modalSubmitActionLabel(__('Replicate'));

        $this->successNotificationTitle(__('Replicated'));

        $this->icon('heroicon-m-square-3-stack-3d');

        $this->requiresConfirmation();

        $this->modalIcon('heroicon-o-square-3-stack-3d');

        $this->action(function () {
            $result = $this->process(function (Collection $records) {
                $records->each(function (Model $record) {
                    $this->replica = $record->replicate($this->getExcludedAttributes());

                    $this->replica->fill($record->attributesToArray());

                    $this->callBeforeReplicaSaved();

                    $this->replica->save();

                    $this->replicateRelationships($record, $this->replica);

                    $this->callAfterReplicaSaved($record, $this->replica);
                });
            });

            try {
                return $result;
            } finally {
                $this->success();
            }
        });
    }

    public function replicateRelationships(Model $original, Model $replica): void
    {
        foreach ($this->relationshipsToReplicate as $relationship) {
            $relation = $original->$relationship();

            if ($relation instanceof BelongsToMany) {
                $replica->$relationship()->sync($relation->pluck($relation->getRelated()->getKeyName()));
            } elseif ($relation instanceof HasMany) {
                $relation->each(function (Model $related) use ($replica, $relationship) {
                    $relatedReplica = $related->replicate($this->getExcludedAttributes());
                    $relatedReplica->{$replica->$relationship()->getForeignKeyName()} = $replica->getKey();
                    $relatedReplica->save();
                });
            } elseif ($relation instanceof HasOne && $relation->exists()) {
                $related = $relation->first();
                $relatedReplica = $related->replicate($this->getExcludedAttributes());
                $relatedReplica->{$replica->$relationship()->getForeignKeyName()} = $replica->getKey();
                $relatedReplica->save();
            }
        }
    }

    public function withReplicatedRelationships(array $relationships): static
    {
        $this->relationshipsToReplicate = $relationships;

        return $this;
    }

    public function afterReplicaSaved(Closure $callback): static
    {
        $this->afterReplicaSaved = $callback;

        return $this;
    }

    public function callAfterReplicaSaved(Model $original, Model $replica): void
    {
        $this->evaluate($this->afterReplicaSaved, [
            'original' => $original,
            'replica' => $replica,
        ]);
    }
}
