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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users','id')->onDelete('cascade');
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types','id')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->enum('status',['pending','approved','rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users','id')->nullOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
