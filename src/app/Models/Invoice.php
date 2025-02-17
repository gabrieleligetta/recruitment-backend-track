<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Invoice
 *
 * @property int $id
 * @property int $user_id
 * @property int $tax_profile_id
 * @property string $invoice_number
 * @property string $description
 * @property string $invoice_date
 * @property float $total_amount
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Invoice|null find(mixed $id)
 * @method static Invoice create(array $attributes)
 * @method static Builder|Invoice query()
 */
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
