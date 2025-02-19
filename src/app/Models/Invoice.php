<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Invoice",
    title: "Invoice",
    description: "Invoice model representing an invoice issued to a user",
    required: ["user_id", "tax_profile_id", "invoice_number", "invoice_date", "total_amount", "status"],
    properties: [
        new OA\Property(
            property: "id",
            description: "Unique identifier for the invoice",
            type: "integer",
            readOnly: true,
            example: 1
        ),
        new OA\Property(
            property: "user_id",
            description: "ID of the user who owns the invoice",
            type: "integer",
            example: 1
        ),
        new OA\Property(
            property: "tax_profile_id",
            description: "ID of the tax profile associated with the invoice",
            type: "integer",
            example: 2
        ),
        new OA\Property(
            property: "invoice_number",
            description: "Invoice number",
            type: "string",
            example: "INV-1001"
        ),
        new OA\Property(
            property: "description",
            description: "Description of the invoice",
            type: "string",
            example: "Invoice for consulting services rendered"
        ),
        new OA\Property(
            property: "invoice_date",
            description: "Date of the invoice",
            type: "string",
            format: "date",
            example: "2021-12-31"
        ),
        new OA\Property(
            property: "total_amount",
            description: "Total amount for the invoice",
            type: "number",
            format: "float",
            example: 1234.56
        ),
        new OA\Property(
            property: "status",
            description: "Current status of the invoice",
            type: "string",
            example: "pending"
        ),
        new OA\Property(
            property: "created_at",
            description: "Timestamp when the invoice was created",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2022-01-01T12:00:00Z",
            nullable: true
        ),
        new OA\Property(
            property: "updated_at",
            description: "Timestamp when the invoice was last updated",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2022-01-02T12:00:00Z",
            nullable: true
        )
    ]
)]
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tax_profile_id',
        'invoice_number',
        'description',
        'invoice_date',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
    ];

    /**
     * Get the user that owns the invoice.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tax profile associated with the invoice.
     */
    public function taxProfile()
    {
        return $this->belongsTo(TaxProfile::class);
    }
}
