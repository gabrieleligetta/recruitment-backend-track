<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TaxProfile;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mockery;
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

    #[Test]
    public function it_returns_server_error_on_invoice_list_exception(): void
    {
        $user = User::factory()->create();

        // Force the InvoiceService to throw an exception when getAll is called.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('getAll')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/invoice/list', []);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
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

    #[Test]
    public function it_returns_404_when_invoice_not_found_on_show(): void
    {
        $user = User::factory()->create();
        $nonExistingId = 9999; // An ID that does not exist

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/invoice/{$nonExistingId}");

        $response->assertStatus(404);
        $this->assertEquals('Invoice not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_invoice_show_exception(): void
    {
        $user = User::factory()->create();

        // Force the InvoiceService to throw an exception when getById is called.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('getById')->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/invoice/1");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
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

    #[Test]
    public function it_returns_validation_errors_when_required_fields_are_missing_on_invoice_store(): void
    {
        $user = User::factory()->create();

        // Simulate a validation exception by forcing the InvoiceService::create method to throw one.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $validator = Validator::make([], ['invoice_number' => 'required']);
            $mock->shouldReceive('create')
                ->andThrow(new ValidationException($validator));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/invoice', [
                //'tax_profile_id' => 1, is omitted to trigger validation error
                'description'    => 'Test Invoice',
                'invoice_date'   => '2025-02-01',
                'total_amount'   => 500.00,
                'status'         => 'pending',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('invoice_number', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_when_not_authorized_to_create_invoice(): void
    {
        $user = User::factory()->create();

        // Force the InvoiceService::create method to throw an authorization exception.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/invoice', [
                'user_id'        => $user->id,
                'tax_profile_id' => 1,
                'invoice_number' => 'INV-9999',
                'description'    => 'Test Invoice',
                'invoice_date'   => '2025-02-01',
                'total_amount'   => 500.00,
                'status'         => 'pending',
            ]);

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_invoice_store_exception(): void
    {
        $user = User::factory()->create();

        // Force the InvoiceService::create method to throw a generic exception.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/invoice', [
                'user_id'        => $user->id,
                'tax_profile_id' => 1,
                'invoice_number' => 'INV-1001',
                'description'    => 'Test Invoice',
                'invoice_date'   => '2025-02-01',
                'total_amount'   => 500.00,
                'status'         => 'pending',
            ]);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
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

    #[Test]
    public function it_returns_404_when_invoice_not_found_on_update(): void
    {
        $user = User::factory()->create();

        // Simulate a "not found" condition by having the update method return null.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('update')->andReturn(null);
        });

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/invoice/9999', ['description' => 'Updated Invoice']);

        $response->assertStatus(404);
        $this->assertEquals('Invoice not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_on_invoice_update_forbidden_exception(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'tax_profile_id' => $taxProfile->id,
            'user_id'        => $user->id,
        ]);

        $this->partialMock(InvoiceService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('update')
                ->with(Mockery::any(), $invoice->id, Mockery::any())
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->putJson("/api/invoice/{$invoice->id}", ['description' => 'Updated Invoice']);

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_invoice_update_exception(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'tax_profile_id' => $taxProfile->id,
            'user_id'        => $user->id,
        ]);

        $this->partialMock(InvoiceService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('update')
                ->with(Mockery::any(), $invoice->id, Mockery::any())
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->putJson("/api/invoice/{$invoice->id}", ['description' => 'Updated Invoice']);

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
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

    #[Test]
    public function it_returns_404_when_invoice_not_found_on_destroy(): void
    {
        $user = User::factory()->create();

        // Simulate a not found condition by having the delete method return false.
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson('/api/invoice/9999');

        $response->assertStatus(404);
        $this->assertEquals('Invoice not found', $response->json('message'));
    }

    #[Test]
    public function it_returns_forbidden_on_invoice_destroy_forbidden_exception(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'tax_profile_id' => $taxProfile->id,
            'user_id'        => $user->id,
        ]);

        $this->partialMock(InvoiceService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('delete')
                ->with(Mockery::any(), $invoice->id)
                ->andThrow(new AuthorizationException("Forbidden"));
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson("/api/invoice/{$invoice->id}");

        $response->assertStatus(403);
        $this->assertEquals('Forbidden', $response->json('message'));
    }

    #[Test]
    public function it_returns_server_error_on_invoice_destroy_exception(): void
    {
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'tax_profile_id' => $taxProfile->id,
            'user_id'        => $user->id,
        ]);

        $this->partialMock(InvoiceService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('delete')
                ->with(Mockery::any(), $invoice->id)
                ->andThrow(new Exception("Simulated exception"));
        });

        $response = $this->actingAs($user, 'api')
            ->deleteJson("/api/invoice/{$invoice->id}");

        $response->assertStatus(500);
        $this->assertEquals('Server Error', $response->json('message'));
    }



}
