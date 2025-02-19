<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TaxProfile",
    title: "Tax Profile",
    description: "Tax profile associated with a user, containing tax-related information.",
    required: ["user_id", "tax_id", "company_name", "address", "country", "city", "zip_code"],
    properties: [
        new OA\Property(
            property: "id",
            description: "Unique identifier for the tax profile",
            type: "integer",
            readOnly: true,
            example: 1
        ),
        new OA\Property(
            property: "user_id",
            description: "ID of the user that owns the tax profile",
            type: "integer",
            example: 1
        ),
        new OA\Property(
            property: "tax_id",
            description: "Tax identification number",
            type: "string",
            example: "TAX123456"
        ),
        new OA\Property(
            property: "company_name",
            description: "Name of the company",
            type: "string",
            example: "Acme Inc."
        ),
        new OA\Property(
            property: "address",
            description: "Company address",
            type: "string",
            example: "123 Main Street"
        ),
        new OA\Property(
            property: "country",
            description: "Country where the company is located",
            type: "string",
            example: "USA"
        ),
        new OA\Property(
            property: "city",
            description: "City where the company is located",
            type: "string",
            example: "New York"
        ),
        new OA\Property(
            property: "zip_code",
            description: "Zip code for the company's location",
            type: "string",
            example: "10001"
        ),
        new OA\Property(
            property: "created_at",
            description: "Timestamp when the tax profile was created",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2022-01-01T12:00:00Z",
            nullable: true
        ),
        new OA\Property(
            property: "updated_at",
            description: "Timestamp when the tax profile was last updated",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2022-01-02T12:00:00Z",
            nullable: true
        )
    ]
)]
class TaxProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tax_id',
        'company_name',
        'address',
        'country',
        'city',
        'zip_code',
    ];

    /**
     * The user that owns the tax profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the invoices associated with the tax profile.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
