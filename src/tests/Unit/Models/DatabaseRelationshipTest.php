<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_verifies_user_taxprofile_invoice_relationships(): void
    {
        // Create a User
        $user = User::factory()->create();

        // Create multiple TaxProfiles for the User
        $taxProfiles = TaxProfile::factory()->count(2)->create(['user_id' => $user->id]);

        // Ensure the User has exactly 2 TaxProfiles
        $this->assertCount(2, $user->taxProfiles);

        // Check that each TaxProfile correctly belongs to the User
        foreach ($taxProfiles as $taxProfile) {
            $this->assertEquals($user->id, $taxProfile->user_id);
        }

        // Create multiple Invoices for one of the TaxProfiles
        $invoices = Invoice::factory()->count(3)->create(['tax_profile_id' => $taxProfiles[0]->id]);

        // Ensure the TaxProfile has exactly 3 Invoices
        $this->assertCount(3, $taxProfiles[0]->invoices);

        // Check that each Invoice belongs to the correct TaxProfile
        foreach ($invoices as $invoice) {
            $this->assertEquals($taxProfiles[0]->id, $invoice->tax_profile_id);
        }
    }

    #[Test]
    public function it_enforces_foreign_key_constraints(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'tax_profile_id' => $taxProfile->id,
        ]);

        // Ensure the database has the created records
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('tax_profiles', ['id' => $taxProfile->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        //Expect deletion of user to also delete associated tax_profiles & invoices
        $user->delete();

        // Ensure cascade delete worked
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('tax_profiles', ['id' => $taxProfile->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function it_cascades_deletion_of_tax_profiles_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);

        // Ensure tax profile exists
        $this->assertDatabaseHas('tax_profiles', ['id' => $taxProfile->id]);

        // Delete the user
        $user->delete();

        // Ensure the tax profile is also deleted
        $this->assertDatabaseMissing('tax_profiles', ['id' => $taxProfile->id]);
    }

    #[Test]
    public function it_cascades_deletion_of_invoices_when_tax_profile_is_deleted(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'tax_profile_id' => $taxProfile->id,
        ]);

        // Ensure invoice exists
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        // Delete the tax profile
        $taxProfile->delete();

        // Ensure the invoice is also deleted
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function it_returns_correct_relationship_types(): void
    {
        $user = new User();
        $taxProfile = new TaxProfile();
        $invoice = new Invoice();

        //User has many TaxProfiles
        $this->assertInstanceOf(HasMany::class, $user->taxProfiles());

        //TaxProfile belongs to User
        $this->assertInstanceOf(BelongsTo::class, $taxProfile->user());

        //TaxProfile has many Invoices
        $this->assertInstanceOf(HasMany::class, $taxProfile->invoices());

        //Invoice belongs to TaxProfile
        $this->assertInstanceOf(BelongsTo::class, $invoice->taxProfile());

        //Invoice belongs to User
        $this->assertInstanceOf(BelongsTo::class, $invoice->user());
    }

    #[Test]
    public function it_prevents_mass_assignment_of_protected_fields(): void
    {
        $invoice = new Invoice([
            'id' => 999, // Should be ignored
            'user_id' => 1,
            'tax_profile_id' => 1,
            'invoice_number' => 'INV-9999',
            'description' => 'Mass Assignment Test',
            'invoice_date' => '2025-02-25',
            'total_amount' => 500.00,
            'status' => 'pending',
        ]);

        $this->assertNull($invoice->id);
        $this->assertEquals('INV-9999', $invoice->invoice_number);
    }

    #[Test]
    public function it_only_allows_fillable_attributes_to_be_assigned(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $taxProfile = TaxProfile::factory()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'tax_profile_id' => $taxProfile->id,
            'invoice_number' => 'INV-1234',
            'description' => 'Valid Invoice',
            'invoice_date' => '2025-02-25',
            'total_amount' => 100.50,
            'status' => 'pending',
            'role' => 'admin', //Not fillable, should be ignored
            'extra_field' => 'Should not exist', //This should be ignored
        ]);

        $this->assertNotNull($invoice->id);
        $this->assertEquals('INV-1234', $invoice->invoice_number);
        $this->assertNull($invoice->extra_field ?? null); // Ensure "extra_field" is not mass assignable
        $this->assertNull($invoice->role ?? null); // Ensure "role" is ignored
    }

    #[Test]
    public function it_prevents_users_from_assigning_admin_role_to_themselves(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        // Try to update the user role to "admin"
        $user->update(['role' => 'admin']);

        // Fetch fresh instance from the database
        $user->refresh();

        // Ensure the role is still "user" and hasn't been changed to "admin"
        $this->assertNotEquals('admin', $user->role);
        $this->assertEquals('user', $user->role);
    }

}
