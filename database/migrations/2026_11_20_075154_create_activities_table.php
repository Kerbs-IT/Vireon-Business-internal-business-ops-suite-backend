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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who did it
            $table->string('action');          // 'completed', 'submitted', 'approved', 'created'...
            $table->morphs('subject');         // subject_type + subject_id (the task, project, leave request)
            $table->string('subject_label');   // snapshot: "API integration", "Mar 25-28"
            $table->foreignId('department_id')->nullable()->index(); // for manager scoping
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
