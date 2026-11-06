<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_registrations', function (Blueprint $table) {
            $table->timestamp('offered_at')->nullable()->after('processed_at');
            $table->foreignId('offered_by')->nullable()->after('offered_at')->constrained('users')->nullOnDelete();
            $table->timestamp('offer_expires_at')->nullable()->after('offered_by');
            $table->string('acceptance_token_hash', 64)->nullable()->after('offer_expires_at');
            $table->timestamp('accepted_at')->nullable()->after('acceptance_token_hash');
            $table->string('accepted_by_name')->nullable()->after('accepted_at');
            $table->ipAddress('accepted_ip')->nullable()->after('accepted_by_name');
            $table->foreignId('enrolled_parent_id')->nullable()->after('enrolled_student_record_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40)->default('supporting');
            $table->string('original_name');
            $table->string('path');
            $table->string('disk', 24)->default('local');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'admission_registration_id']);
        });

        Schema::create('admission_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('mode', 24)->default('in_person');
            $table->string('location')->nullable();
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->string('outcome', 24)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'scheduled_at']);
        });

        Schema::create('admission_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['admission_registration_id', 'created_at'], 'admission_notes_registration_date_index');
        });

        Schema::create('admission_fee_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('NGN');
            $table->string('payment_method', 32);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmer_name');
            $table->timestamp('confirmed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'confirmed_at']);
        });

        DB::table('admission_registrations')->where('status', 'rejected')->update(['status' => 'declined']);
        DB::table('admission_registrations')->where('status', 'approved')->whereNotNull('enrolled_at')->update(['status' => 'enrolled']);
        DB::table('admission_registrations')->where('status', 'approved')->whereNull('enrolled_at')->update(['status' => 'accepted']);

        $legacyDocuments = DB::table('admission_registrations')
            ->whereNotNull('document_path')
            ->where('document_path', '!=', '')
            ->get(['id', 'school_id', 'document_path', 'document_name', 'created_at']);

        foreach ($legacyDocuments as $document) {
            DB::table('admission_documents')->insert([
                'admission_registration_id' => $document->id,
                'school_id' => $document->school_id,
                'category' => 'legacy_supporting',
                'original_name' => $document->document_name ?: basename($document->document_path),
                'path' => $document->document_path,
                'disk' => 'public',
                'created_at' => $document->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_fee_confirmations');
        Schema::dropIfExists('admission_internal_notes');
        Schema::dropIfExists('admission_interviews');
        Schema::dropIfExists('admission_documents');

        Schema::table('admission_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrolled_parent_id');
            $table->dropConstrainedForeignId('offered_by');
            $table->dropColumn([
                'offered_at',
                'offer_expires_at',
                'acceptance_token_hash',
                'accepted_at',
                'accepted_by_name',
                'accepted_ip',
            ]);
        });
    }
};
