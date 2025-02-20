<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "User",
    title: "User Model",
    description: "User entity for authentication and user management",
    required: ["name", "email", "password"],
    properties: [
        new OA\Property(
            property: "id",
            description: "Unique identifier for the user",
            type: "integer",
            readOnly: true,
            example: 1
        ),
        new OA\Property(
            property: "name",
            description: "Name of the user",
            type: "string",
            example: "John Doe"
        ),
        new OA\Property(
            property: "email",
            description: "Email address of the user",
            type: "string",
            format: "email",
            example: "john@example.com"
        ),
        new OA\Property(
            property: "role",
            description: "Role assigned to the user",
            type: "string",
            enum: ["user", "admin"],
            example: "user"
        ),
        new OA\Property(
            property: "password",
            description: "User's password (write-only)",
            type: "string",
            writeOnly: true
        ),
        new OA\Property(
            property: "email_verified_at",
            description: "Timestamp when the user's email was verified",
            type: "string",
            format: "date-time",
            example: "2023-01-15T13:45:30Z",
            nullable: true
        ),
        new OA\Property(
            property: "created_at",
            description: "Timestamp when the user was created",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2023-01-15T12:00:00Z"
        ),
        new OA\Property(
            property: "updated_at",
            description: "Timestamp when the user was last updated",
            type: "string",
            format: "date-time",
            readOnly: true,
            example: "2023-02-01T08:00:00Z"
        )
    ]
)]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Removed: public mixed $id;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'role',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Relationship: A User has many TaxProfiles
     */
    public function taxProfiles(): HasMany
    {
        return $this->hasMany(TaxProfile::class);
    }

    /**
     * Relationship: A User has many Invoices (through TaxProfile)
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



}
