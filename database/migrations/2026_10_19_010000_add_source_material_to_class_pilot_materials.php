<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_pilot_materials', function (Blueprint $table): void {
            $table->foreignId('source_material_id')->nullable()->after('subject_id')->constrained('class_pilot_materials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_pilot_materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_material_id');
        });
    }
};
