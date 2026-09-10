<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title');
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('responsibilities')->nullable();
            $table->date('joined_on')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['school_id', 'is_public', 'display_order']);
        });

        $departments = [
            ['Management and Administration', 'management', 10], ['Teaching Staff', 'teachers', 20],
            ['Student Support and Pastoral Care', 'student_support', 30], ['Non-Teaching Staff', 'non_teaching', 40],
            ['Maintenance and Facilities', 'maintenance', 50], ['Health and Safety', 'health_safety', 60],
            ['Security', 'security', 70], ['Transport', 'transport', 80], ['Catering', 'catering', 90],
        ];
        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            foreach ($departments as [$name, $category, $order]) {
                DB::table('staff_departments')->insert(['school_id' => $schoolId, 'name' => $name, 'category' => $category,
                    'display_order' => $order, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $managementId = DB::table('staff_departments')->where('school_id', $schoolId)->where('category', 'management')->value('id');
            $teacherId = DB::table('staff_departments')->where('school_id', $schoolId)->where('category', 'teachers')->value('id');
            $staffUsers = DB::table('users as u')->join('model_has_roles as mr', 'mr.model_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')->where('mr.model_type', 'App\\Models\\User')
                ->where('u.school_id', $schoolId)->whereIn('r.name', ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'])
                ->select('u.id', 'r.name')->get()->unique('id');
            foreach ($staffUsers as $user) {
                $isTeacher = $user->name === 'teacher';
                DB::table('staff_profiles')->insert(['school_id' => $schoolId, 'user_id' => $user->id,
                    'staff_department_id' => $isTeacher ? $teacherId : $managementId,
                    'job_title' => $isTeacher ? 'Teacher' : 'School Administrator', 'is_public' => true,
                    'display_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('staff_departments');
    }
};
