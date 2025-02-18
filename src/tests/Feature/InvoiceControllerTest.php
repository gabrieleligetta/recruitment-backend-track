<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TaxProfile;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_correctly_lists_invoices_based_on_user_role(): void
    {
        // ✅ Create an admin and two regular users
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        // ✅ Create tax profiles for both users
        $taxProfile1 = TaxProfile::factory()->create(['user_id' => $user1->id]);
        $taxProfile2 = TaxProfile::factory()->create(['user_id' => $user2->id]);

        // ✅ Create invoices for both users
        Invoice::factory()->count(2)->create(['tax_profile_id' => $taxProfile1->id, 'user_id' => $user1->id]);
        Invoice::factory()->count(3)->create(['tax_profile_id' => $taxProfile2->id, 'user_id' => $user2->id]);

        // ✅ Admin should see all invoices (2 + 3 = 5)
        $adminResponse = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice/list', []);

        $adminResponse->assertStatus(200);
        $this->assertEquals(5, count($adminResponse->json('data')));

        // ✅ User1 should only see their own invoices (2)
        $user1Response = $this->actingAs($user1, 'api')
            ->postJson('/api/invoice/list', []);

        $user1Response->assertStatus(200);
        $this->assertEquals(2, count($user1Response->json('data')));

        // ✅ User2 should only see their own invoices (3)
        $user2Response = $this->actingAs($user2, 'api')
            ->postJson('/api/invoice/list', []);

        $user2Response->assertStatus(200);
        $this->assertEquals(3, count($user2Response->json('data')));
    }


    /*
    |--------------------------------------------------------------------------
    | TEST: List Invoices (POST /api/invoice/list)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_show_an_invoice(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create(['tax_profile_id' => $taxProfile->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/invoice/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id'             => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Show Invoice (GET /api/invoice/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_denies_access_to_non_owner_users_for_invoices(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user1->id]);
        $invoice = Invoice::factory()->create(['tax_profile_id' => $taxProfile->id, 'user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'api')
            ->getJson("/api/invoice/{$invoice->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_users_to_create_an_invoice(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/invoice', [
                'user_id' => $user->id,
                'tax_profile_id' => $taxProfile->id,
                'invoice_number' => 'INV-1000',
                'description'    => 'Test invoice',
                'invoice_date'   => '2025-02-01',
                'total_amount'   => 500.00,
                'status'         => 'pending',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-1000']);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Create Invoice (POST /api/invoice)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_allows_admins_to_create_an_invoice_for_any_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice', [
                'user_id'        => $user->id,
                'tax_profile_id' => $taxProfile->id,
                'invoice_number' => 'INV-2000',
                'description'    => 'Admin created invoice',
                'invoice_date'   => '2025-02-01',
                'total_amount'   => 700.00,
                'status'         => 'paid',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-2000']);
    }

    #[Test]
    public function it_allows_admin_or_owner_to_update_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create(['tax_profile_id' => $taxProfile->id, 'user_id' => $user->id]);

        $response = $this->actingAs($admin, 'api')
            ->putJson("/api/invoice/{$invoice->id}", ['description' => 'Updated Invoice']);
        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Update Invoice (PUT /api/invoice/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_allows_admin_or_owner_to_delete_an_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create(['tax_profile_id' => $taxProfile->id, 'user_id' => $user->id]);

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/invoice/{$invoice->id}");
        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Delete Invoice (DELETE /api/invoice/{id})
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_prevents_non_owner_users_from_deleting_invoices(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user1->id]);
        $invoice = Invoice::factory()->create(['tax_profile_id' => $taxProfile->id, 'user_id' => $user1->id]);

        $response = $this->actingAs($user2, 'api')
            ->deleteJson("/api/invoice/{$invoice->id}");

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}
