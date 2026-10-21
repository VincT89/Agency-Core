<?php

namespace App\Domain\Finance\Actions;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecurrence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteExpenseCategory
{
    public function execute(int $id): void
    {
        Gate::authorize('create', Expense::class);
        try {
            DB::transaction(function () use ($id) {
                $category = ExpenseCategory::lockForUpdate()->findOrFail($id);
                $children = $category->children()->lockForUpdate()->pluck('id');
                $ids = $children->push($category->id)->all();
                /** Anche le spese annullate e le ricorrenze sospese conservano la propria classificazione storica. */
                foreach ([Expense::class, ExpenseRecurrence::class] as $model) {
                    if ($model::where(fn ($query) => $query->whereIn('category_id', $ids)->orWhereIn('subcategory_id', $ids))->exists()) {
                        $this->inUse();
                    }
                }
                $category->children()->delete();
                $category->delete();
            });
        } catch (QueryException $exception) {
            /** I vincoli impediscono la cancellazione se una spesa viene collegata durante la verifica. */
            if ($exception->getCode() === '23000') $this->inUse();
            throw $exception;
        }
    }

    private function inUse(): never
    {
        throw ValidationException::withMessages(['delete' => 'La categoria o una sua sottocategoria è usata da spese o ricorrenze. Puoi modificarla o archiviarla togliendo “Disponibile per nuove spese”.']);
    }
}
