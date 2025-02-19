<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Mockery;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    protected InvoiceService $invoiceService;
    protected $mockInvoiceRepository;

    #[Test]
    public function it_returns_a_paginated_list_of_invoices(): void
    {
        $user = User::factory()->create();

        $fakePaginator = new Paginator(new Collection([]), 0, 10);

        $this->mockInvoiceRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn($fakePaginator);

        $result = $this->invoiceService->getAll($user, []);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_finds_an_invoice_by_id(): void
    {
        $authUser = User::factory()->create();
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $authUser->id]);

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $result = $this->invoiceService->getById($authUser, 1);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals(1, $result->id);
    }

    #[Test]
    public function it_denies_access_to_non_owner_users_when_finding_invoice(): void
    {
        $authUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $otherUser->id]);

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $this->expectException(AuthorizationException::class);

        $this->invoiceService->getById($authUser, 1);
    }

    #[Test]
    public function it_creates_a_new_invoice(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $taxProfile = TaxProfile::factory()->create(['user_id' => $authUser->id]); //Ensure a valid tax profile exists

        $invoiceData = [
            'user_id'        => $authUser->id,
            'tax_profile_id' => $taxProfile->id, //Use the created tax profile
            'invoice_number' => 'INV-1234',
            'description'    => 'Test Invoice',
            'invoice_date'   => '2025-02-01',
            'total_amount'   => 500.00,
            'status'         => 'pending',
        ];

        $invoice = Invoice::factory()->make($invoiceData);

        $this->mockInvoiceRepository
            ->shouldReceive('create')
            ->with(Mockery::subset($invoiceData))
            ->once()
            ->andReturn($invoice);

        $result = $this->invoiceService->create($authUser, $invoiceData);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals('INV-1234', $result->invoice_number);
    }


    #[Test]
    public function it_updates_an_existing_invoice(): void
    {
        $authUser = User::factory()->create();
        $taxProfile = TaxProfile::factory()->create(['user_id' => $authUser->id]); //Ensure a valid tax profile exists
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $authUser->id]);

        $updatedData = [
            'tax_profile_id' => $taxProfile->id, //Use the created tax profile
            'user_id'        => $authUser->id, //Ensure user_id is passed
            'invoice_number' => 'INV-5678',
        ];

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $updatedInvoice = clone $invoice;
        $updatedInvoice->invoice_number = 'INV-5678';

        $this->mockInvoiceRepository
            ->shouldReceive('update')
            ->with(1, Mockery::subset($updatedData))
            ->once()
            ->andReturn($updatedInvoice);

        $result = $this->invoiceService->update($authUser, 1, $updatedData);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals('INV-5678', $result->invoice_number);
    }


    #[Test]
    public function it_denies_access_to_non_owner_users_when_updating_invoice(): void
    {
        $authUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $otherUser->id]);

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $this->expectException(AuthorizationException::class);

        $this->invoiceService->update($authUser, 1, ['invoice_number' => 'INV-5678']);
    }

    #[Test]
    public function it_deletes_an_invoice(): void
    {
        $authUser = User::factory()->create();
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $authUser->id]);

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $this->mockInvoiceRepository
            ->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->invoiceService->delete($authUser, 1);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_denies_access_to_non_owner_users_when_deleting_invoice(): void
    {
        $authUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->make(['id' => 1, 'user_id' => $otherUser->id]);

        $this->mockInvoiceRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($invoice);

        $this->expectException(AuthorizationException::class);

        $this->invoiceService->delete($authUser, 1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        //Mock InvoiceRepository
        $this->mockInvoiceRepository = Mockery::mock(InvoiceRepository::class);

        //Inject the mocked repository into InvoiceService
        $this->invoiceService = new InvoiceService($this->mockInvoiceRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
