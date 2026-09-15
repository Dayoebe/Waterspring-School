<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('email_is_placeholder')->default(false)->after('email');
            $table->boolean('requires_password_change')->default(false)->after('password');
        });

        DB::table('users')
            ->where('email', 'like', '%.admission.local')
            ->update(['email_is_placeholder' => true]);

        if (Schema::hasTable('admission_registrations')) {
            DB::table('users')
                ->whereIn('id', DB::table('admission_registrations')->whereNotNull('enrolled_user_id')->select('enrolled_user_id'))
                ->update(['requires_password_change' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_is_placeholder', 'requires_password_change']);
        });
    }
};
