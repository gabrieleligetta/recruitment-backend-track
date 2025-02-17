<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\TaxProfile
 *
 * @property int $id
 * @property int $user_id
 * @property string $tax_id
 * @property string $company_name
 * @property string $address
 * @property string $country
 * @property string $city
 * @property string $zip_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static TaxProfile|null find(mixed $id)
 * @method static TaxProfile create(array $attributes)
 * @method static Builder|TaxProfile query()
 */
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
