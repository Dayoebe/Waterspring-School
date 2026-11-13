<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->unsignedBigInteger('default_amount')->default(0)->after('description');
            $table->string('status', 24)->default('draft')->after('default_amount');
            $table->unsignedInteger('approval_version')->default(0)->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_version')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index(['fee_category_id', 'status'], 'fees_category_status_index');
        });

        DB::table('fees')->update([
            'status' => 'approved',
            'approval_version' => 1,
            'approved_at' => now(),
        ]);

        Schema::create('fee_approval_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('deduplication_key', 190)->unique();
            $table->string('status', 24)->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['fee_id', 'status'], 'fee_approval_delivery_status_index');
        });

        $permission = Permission::findOrCreate('approve fee', 'web');
        Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($query) => $query->where('name', 'update fee'))
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        foreach (['principal', 'admin'] as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()?->givePermissionTo($permission);
        }

        $readInvoices = Permission::findOrCreate('read fee invoice', 'web');
        Role::query()
            ->where('name', 'parent')
            ->where('guard_name', 'web')
            ->first()?->givePermissionTo($readInvoices);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_approval_notification_deliveries');

        Schema::table('fees', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex('fees_category_status_index');
            $table->dropColumn(['default_amount', 'status', 'approval_version', 'approved_by', 'approved_at']);
        });

        Permission::query()->where('name', 'approve fee')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
