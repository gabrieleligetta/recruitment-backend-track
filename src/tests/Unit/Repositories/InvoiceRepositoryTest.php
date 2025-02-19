<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Models\TaxProfile;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceRepository $invoiceRepository;

    #[Test]
    public function it_can_filter_invoices_by_invoice_date_range(): void
    {
        // Create a user, tax profile
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);

        // Create 3 invoices with different dates
        Invoice::factory()->create([
            'user_id'        => $user->id,           // If your DB demands user_id
            'tax_profile_id' => $taxProfile->id,
            'invoice_date'   => '2023-01-01',
        ]);
        Invoice::factory()->create([
            'user_id'        => $user->id,
            'tax_profile_id' => $taxProfile->id,
            'invoice_date'   => '2023-02-15',
        ]);
        Invoice::factory()->create([
            'user_id'        => $user->id,
            'tax_profile_id' => $taxProfile->id,
            'invoice_date'   => '2023-03-10',
        ]);

        // Filter for date in range [2023-01-15 .. 2023-03-01]
        $params = [
            'filters' => [
                [
                    'field'      => 'invoice_date',
                    'value'      => '2023-01-15',
                    'rangeValue' => '2023-03-01',
                    'fieldType'  => 'date',
                    'operator'   => 'inRange',
                ],
            ],
        ];

        $results = $this->invoiceRepository->all($params);

        // Expect only 1 invoice, the one from 2023-02-15
        $this->assertCount(1, $results);

        // If 'invoice_date' is cast to a Carbon instance in your Invoice model,
        // you can safely do ->format('Y-m-d')
        $invoiceDate = $results->getCollection()->first()->invoice_date;
        $this->assertEquals('2023-02-15', $invoiceDate->format('Y-m-d'));
    }

    #[Test]
    public function it_can_create_a_new_invoice(): void
    {
        // Create user + tax profile
        $user = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $user->id]);

        // If your DB demands user_id in the invoices table, pass it
        $data = [
            'user_id'        => $user->id,
            'tax_profile_id' => $taxProfile->id,
            'invoice_number' => 'INV-2025',
            'description'    => 'Some description',
            'invoice_date'   => '2025-02-01',
            'total_amount'   => 999.99,
            'status'         => 'pending',
        ];

        $invoice = $this->invoiceRepository->create($data);

        $this->assertNotNull($invoice->id);
        $this->assertEquals($user->id, $invoice->user_id);
        $this->assertEquals($taxProfile->id, $invoice->tax_profile_id);
        $this->assertEquals('INV-2025', $invoice->invoice_number);
        $this->assertEquals('pending', $invoice->status);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Drop all tables and re-run migrations for a fresh DB
        Artisan::call('migrate:fresh');
        $this->invoiceRepository = new InvoiceRepository();
    }
}
