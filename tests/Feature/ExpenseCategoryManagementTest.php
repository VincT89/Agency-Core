<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\SaveExpenseCategory;
use App\Enums\UserRole;
use App\Livewire\Expenses\ExpenseCategories;
use App\Livewire\Expenses\ExpensesIndex;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'password_changed_at' => now()]));
    }

    private function category(string $name, ?ExpenseCategory $parent = null): ExpenseCategory
    {
        return app(SaveExpenseCategory::class)->execute(['name' => $name, 'parent_id' => $parent?->id]);
    }

    public function test_unused_categories_can_be_renamed_and_deleted_and_their_badges_follow_the_changes(): void
    {
        $category = $this->category('Software');
        $index = Livewire::test(ExpensesIndex::class)->assertSee('Software (0)');
        $manager = Livewire::test(ExpenseCategories::class)->call('edit', $category->id)
            ->set('name', 'Software e strumenti')->call('save')->assertHasNoErrors();
        $index->call('$refresh')->assertSee('Software e strumenti (0)')->assertDontSee('Software (0)');
        $manager->call('delete', $category->id)->assertHasNoErrors();
        $index->call('$refresh')->assertDontSee('Software e strumenti');
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_deleting_a_subcategory_keeps_the_parent_and_siblings(): void
    {
        $parent = $this->category('Ufficio');
        $child = $this->category('Affitto', $parent);
        $sibling = $this->category('Utenze', $parent);
        Livewire::test(ExpenseCategories::class)->call('edit', $child->id)->set('name', 'Affitto locali')->call('save')->assertHasNoErrors()
            ->call('delete', $child->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('expense_categories', ['id' => $child->id]);
        $this->assertDatabaseHas('expense_categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('expense_categories', ['id' => $sibling->id, 'parent_id' => $parent->id]);
    }

    public function test_deleting_an_unused_parent_removes_only_its_children_and_resets_the_editor(): void
    {
        $parent = $this->category('Ufficio');
        $child = $this->category('Affitto', $parent);
        $other = $this->category('Marketing');
        Livewire::test(ExpenseCategories::class)->call('edit', $child->id)->call('delete', $parent->id)
            ->assertHasNoErrors()->assertSet('editingId', null)->assertSet('name', '');
        $this->assertDatabaseCount('expense_categories', 1);
        $this->assertTrue($other->is(ExpenseCategory::sole()));
    }

    public function test_spent_categories_cannot_be_deleted_even_when_the_expense_is_cancelled(): void
    {
        $parent = $this->category('Ufficio');
        $child = $this->category('Utenze', $parent);
        $expense = Expense::create(['user_id' => auth()->id(), 'title' => 'Spesa storica', 'amount' => 15,
            'expense_date' => today(), 'status' => 'cancelled', 'category_id' => $parent->id, 'subcategory_id' => $child->id]);
        $original = $expense->fresh()->getAttributes();
        foreach ([$parent, $child] as $category) {
            Livewire::test(ExpenseCategories::class)->call('delete', $category->id)->assertHasErrors('delete');
            $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
        }
        $this->assertSame($original, $expense->fresh()->getAttributes());
        Livewire::test(ExpenseCategories::class)->call('edit', $parent->id)->set('active', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($parent->fresh()->active);
    }

    public function test_categories_used_by_suspended_recurrences_cannot_be_deleted(): void
    {
        $parent = $this->category('Software');
        $child = $this->category('Licenze', $parent);
        $recurrence = ExpenseRecurrence::create(['user_id' => auth()->id(), 'title' => 'Licenza annuale', 'amount' => 15,
            'frequency' => 'yearly', 'starts_on' => today(), 'active' => false, 'category_id' => $parent->id, 'subcategory_id' => $child->id]);
        foreach ([$parent, $child] as $category) {
            Livewire::test(ExpenseCategories::class)->call('delete', $category->id)->assertHasErrors('delete');
        }
        $this->assertDatabaseHas('expense_recurrences', ['id' => $recurrence->id, 'category_id' => $parent->id, 'subcategory_id' => $child->id]);
        $this->assertDatabaseCount('expense_categories', 2);
    }

    public function test_delete_checks_finance_permissions_again_on_the_action(): void
    {
        $category = $this->category('Ufficio');
        $manager = Livewire::test(ExpenseCategories::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::Developer, 'password_changed_at' => now()]));
        $manager->call('delete', $category->id)->assertForbidden();
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }
}
