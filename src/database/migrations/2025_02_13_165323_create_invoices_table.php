<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Links to tax_profiles table
            $table->foreignId('tax_profile_id')->constrained('tax_profiles')->onDelete('cascade'); // Links to tax_profiles table
            $table->string('invoice_number')->unique();
            $table->string('description');
            $table->date('invoice_date');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'canceled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
