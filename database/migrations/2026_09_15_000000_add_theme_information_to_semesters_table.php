<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            $table->string('theme_title')->nullable()->after('name');
            $table->text('theme_description')->nullable()->after('theme_title');
            $table->string('theme_scripture')->nullable()->after('theme_description');
            $table->text('theme_focus')->nullable()->after('theme_scripture');
            $table->string('theme_color', 7)->default('#0875a5')->after('theme_focus');
            $table->date('starts_on')->nullable()->after('theme_color');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table): void {
            $table->dropColumn([
                'theme_title', 'theme_description', 'theme_scripture',
                'theme_focus', 'theme_color', 'starts_on', 'ends_on',
            ]);
        });
    }
};
