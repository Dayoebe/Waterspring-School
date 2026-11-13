<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->string('default_waiver_type', 16)->default('none')->after('default_amount');
            $table->unsignedBigInteger('default_waiver_amount')->default(0)->after('default_waiver_type');
            $table->unsignedSmallInteger('default_waiver_basis_points')->default(0)->after('default_waiver_amount');
            $table->unsignedBigInteger('default_fine')->default(0)->after('default_waiver_basis_points');
        });
    }

    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->dropColumn([
                'default_waiver_type',
                'default_waiver_amount',
                'default_waiver_basis_points',
                'default_fine',
            ]);
        });
    }
};
