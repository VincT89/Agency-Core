<?php

namespace App\Domain\Finance\Actions;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveExpenseCategory
{
    public function execute(array $data, ?ExpenseCategory $category = null): ExpenseCategory
    {
        Gate::authorize('create', Expense::class);
        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name'] ?? ''));
        $data = Validator::make($data, [
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->whereNull('parent_id')],
            'active' => 'sometimes|boolean',
        ])->validate();

        return DB::transaction(function () use ($data, $category) {
            $parentId = $category?->parent_id ?? ($data['parent_id'] ?? null);
            if ($category && (int) ($data['parent_id'] ?? 0) !== (int) $category->parent_id) {
                throw ValidationException::withMessages(['parent_id' => 'La categoria principale non può essere cambiata.']);
            }
            $parent = $parentId ? ExpenseCategory::lockForUpdate()->findOrFail($parentId) : null;
            if ($parent && ! $parent->active && ($data['active'] ?? true)) {
                throw ValidationException::withMessages(['parent_id' => 'Riattiva prima la categoria principale.']);
            }
            $key = ExpenseCategory::identity($data['name'], $parentId);
            $duplicate = ExpenseCategory::where('identity_key', $key)->first();
            if ($duplicate && $duplicate->id !== $category?->id) {
                throw ValidationException::withMessages(['name' => 'Questa categoria esiste già, anche tra quelle archiviate.']);
            }
            $category ??= new ExpenseCategory();
            $category->fill($data + ['active' => true]);
            $category->identity_key = $key;
            $category->save();
            /** Legacy consumers still read the label, while reports group by stable category identifiers. */
            if ($category->parent_id === null) {
                foreach (['expenses', 'expense_recurrences'] as $table) {
                    DB::table($table)->where('category_id', $category->id)->update(['category' => $category->name]);
                }
            }
            return $category;
        });
    }
}
