<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TaxProfile;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_paginate_and_sort_invoices_by_invoice_number(): void
    {

        /*
        |--------------------------------------------------------------------------
        | TEST: List Invoices (POST /api/invoice/list)
        |--------------------------------------------------------------------------
        */


        // 1) Create an admin (who can see all invoices).
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'ZZZ Admin', // not required, just consistent with prior examples
        ]);

        // 2) Create multiple invoices with predictable invoice_number values.
        //    This ensures we can confirm they come back in ascending order.
        //    We'll create 15 distinct invoice_numbers, so we end up with 3 pages
        //    if 'limit' is 5.
        $invoiceNumbers = [
            'INV-001', 'INV-002', 'INV-003', 'INV-004', 'INV-005',
            'INV-006', 'INV-007', 'INV-008', 'INV-009', 'INV-010',
            'INV-011', 'INV-012', 'INV-013', 'INV-014', 'INV-015',
        ];

        foreach ($invoiceNumbers as $number) {
            // Attach these invoices to any user—here we just associate them
            // with the admin for simplicity, but you could create distinct
            // user(s) if that’s relevant.
            Invoice::factory()->create([
                'user_id'         => $admin->id,
                'tax_profile_id'  => TaxProfile::factory()->create(['user_id' => $admin->id])->id,
                'invoice_number'  => $number,
            ]);
        }

        // 3) PAGE 1: Request the first 5 invoices, sorted ascending by invoice_number
        $responsePage1 = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice/list', [
                'filters' => [],
                'sort'    => ['field' => 'invoice_number', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 1,
            ]);

        $responsePage1->assertStatus(200);
        $responsePage1->assertJsonStructure([
            'data' => [['id', 'invoice_number', 'status']],
            'current_page',
            'total',
            'per_page',
            'last_page',
        ]);

        // Extract the first page data
        $page1Data = $responsePage1->json('data');
        $this->assertCount(5, $page1Data, 'Page 1 should contain 5 invoices');

        // Check the invoice_number sequence for the first page
        $actualNumbersPage1 = array_column($page1Data, 'invoice_number');
        $this->assertEquals(
            ['INV-001', 'INV-002', 'INV-003', 'INV-004', 'INV-005'],
            $actualNumbersPage1,
            'Page 1 invoice numbers are not in the expected order'
        );

        // Verify pagination stats
        $this->assertEquals(1, $responsePage1->json('current_page'));
        $this->assertEquals(15, $responsePage1->json('total'));
        $this->assertEquals(5, $responsePage1->json('per_page'));
        $this->assertEquals(3, $responsePage1->json('last_page')); // 15 total, 5 per page => 3 pages

        // 4) PAGE 2
        $responsePage2 = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice/list', [
                'filters' => [],
                'sort'    => ['field' => 'invoice_number', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 2,
            ]);

        $responsePage2->assertStatus(200);
        $page2Data = $responsePage2->json('data');
        $this->assertCount(5, $page2Data, 'Page 2 should contain 5 invoices');

        $actualNumbersPage2 = array_column($page2Data, 'invoice_number');
        $this->assertEquals(
            ['INV-006', 'INV-007', 'INV-008', 'INV-009', 'INV-010'],
            $actualNumbersPage2,
            'Page 2 invoice numbers are not in the expected order'
        );

        // 5) PAGE 3
        $responsePage3 = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice/list', [
                'filters' => [],
                'sort'    => ['field' => 'invoice_number', 'direction' => 'asc'],
                'limit'   => 5,
                'page'    => 3,
            ]);

        $responsePage3->assertStatus(200);
        $page3Data = $responsePage3->json('data');
        $this->assertCount(5, $page3Data, 'Page 3 should contain the remaining 5 invoices');

        $actualNumbersPage3 = array_column($page3Data, 'invoice_number');
        $this->assertEquals(
            ['INV-011', 'INV-012', 'INV-013', 'INV-014', 'INV-015'],
            $actualNumbersPage3,
            'Page 3 invoice numbers are not in the expected order'
        );

        // Confirm final pagination info
        $this->assertEquals(3, $responsePage3->json('current_page'));
        $this->assertEquals(15, $responsePage3->json('total'));
        $this->assertEquals(5, $responsePage3->json('per_page'));
        $this->assertEquals(3, $responsePage3->json('last_page'));
    }


    #[Test]
    public function it_correctly_lists_invoices_based_on_user_role(): void
    {
        //Create an admin and two regular users
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        //Create tax profiles for both users
        $taxProfile1 = TaxProfile::factory()->create(['user_id' => $user1->id]);
        $taxProfile2 = TaxProfile::factory()->create(['user_id' => $user2->id]);

        //Create invoices for both users
        Invoice::factory()->count(2)->create(['tax_profile_id' => $taxProfile1->id, 'user_id' => $user1->id]);
        Invoice::factory()->count(3)->create(['tax_profile_id' => $taxProfile2->id, 'user_id' => $user2->id]);

        //Admin should see all invoices (2 + 3 = 5)
        $adminResponse = $this->actingAs($admin, 'api')
            ->postJson('/api/invoice/list', []);

        $adminResponse->assertStatus(200);
        $this->assertEquals(5, count($adminResponse->json('data')));

        //User1 should only see their own invoices (2)
        $user1Response = $this->actingAs($user1, 'api')
            ->postJson('/api/invoice/list', []);

        $user1Response->assertStatus(200);
        $this->assertEquals(2, count($user1Response->json('data')));

        //User2 should only see their own invoices (3)
        $user2Response = $this->actingAs($user2, 'api')
            ->postJson('/api/invoice/list', []);

        $user2Response->assertStatus(200);
        $this->assertEquals(3, count($user2Response->json('data')));
    }

    /*
    |--------------------------------------------------------------------------
    | TEST: Show Invoice (GET /api/invoice/{id})
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

    /*
    |--------------------------------------------------------------------------
    | TEST: Create Invoice (POST /api/invoice)
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | TEST: Update Invoice (PUT /api/invoice/{id})
    |--------------------------------------------------------------------------
    */

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
    | TEST: Delete Invoice (DELETE /api/invoice/{id})
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
}
