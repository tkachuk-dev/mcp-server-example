<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('user_id');
            $table->string('plan', 64);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 16);
            $table->timestamp('started_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user_projections');
            $table->index(['tenant_id', 'started_at']);
            $table->index(['tenant_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
