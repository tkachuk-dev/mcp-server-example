<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('payment_id')->constrained('payments');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
