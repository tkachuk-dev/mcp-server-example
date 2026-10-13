<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local projection of users owned by the Users service, kept up to date by user.* events.
        Schema::create('user_projections', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('status', 16);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_projections');
    }
};
