<?php

namespace App\Domain\Core\Queries;

use Illuminate\Database\Eloquent\Builder;

final class RecentActivity
{
    public static function apply(Builder $query, bool $includeComments = true): Builder
    {
        $model = $query->getModel();
        $grammar = $query->getQuery()->getGrammar();
        $updatedAt = $grammar->wrap($model->qualifyColumn('updated_at'));
        $createdAt = $grammar->wrap($model->qualifyColumn('created_at'));
        $recordDate = "COALESCE({$updatedAt}, {$createdAt})";

        $query->addSelect($model->qualifyColumn('*'))
            ->withCasts(['last_activity_at' => 'datetime']);

        if (! $includeComments) {
            return $query->selectRaw("{$recordDate} AS last_activity_at");
        }

        $comments = $model->comments();
        $commentDate = $grammar->wrap($comments->getRelated()->qualifyColumn('created_at'));

        /** La data di creazione include le risposte esistenti senza contare callback e tentativi di consegna come nuove attività. */
        $latestActivity = $comments->getRelated()->newQuery()
            ->whereColumn($comments->getQualifiedForeignKeyName(), $comments->getQualifiedParentKeyName())
            ->selectRaw("CASE WHEN {$recordDate} IS NULL OR MAX({$commentDate}) > {$recordDate} THEN MAX({$commentDate}) ELSE {$recordDate} END");

        return $query->selectSub($latestActivity, 'last_activity_at');
    }
}
