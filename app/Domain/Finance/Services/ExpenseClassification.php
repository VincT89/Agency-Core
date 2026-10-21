<?php

namespace App\Domain\Finance\Services;

use App\Models\ExpenseCategory;
use Illuminate\Validation\ValidationException;

class ExpenseClassification
{
    public function resolve(array $data, ?int $currentCategory = null, ?int $currentSubcategory = null): array
    {
        if (! array_key_exists('category_id', $data)) {
            /** Keep older integrations and recurring expenses compatible with their existing labels. */
            if (filled($data['category'] ?? null)) {
                $name = trim(preg_replace('/\s+/u', ' ', $data['category']));
                $category = ExpenseCategory::firstOrCreate(['identity_key' => ExpenseCategory::identity($name, null)], [
                    'name' => $name, 'active' => true,
                ]);
                $data['category_id'] = $category->id;
            } else {
                return $data;
            }
        }
        $category = filled($data['category_id']) ? ExpenseCategory::find($data['category_id']) : null;
        $subcategory = filled($data['subcategory_id'] ?? null) ? ExpenseCategory::find($data['subcategory_id']) : null;
        if (filled($data['category_id']) && (! $category || $category->parent_id !== null
            || (! $category->active && $category->id !== $currentCategory))) {
            throw ValidationException::withMessages(['category_id' => 'Seleziona una categoria principale attiva.']);
        }
        if (filled($data['subcategory_id'] ?? null) && (! $subcategory || ! $category
            || $subcategory->parent_id !== $category->id
            || ((! $subcategory->active || ! $category->active) && $subcategory->id !== $currentSubcategory))) {
            throw ValidationException::withMessages(['subcategory_id' => 'Seleziona una sottocategoria attiva della categoria scelta.']);
        }
        return array_replace($data, [
            'category_id' => $category?->id, 'subcategory_id' => $subcategory?->id, 'category' => $category?->name,
        ]);
    }
}
