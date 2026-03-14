<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRolesTable();
        $this->upgradeUsersTable();
        $this->createAcademicCoreTables();
        $this->upgradeCoreDomainTables();
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance', 'enrollment_id')) {
                $table->dropUnique('attendance_enrollment_date_unique');
                $table->dropIndex('attendance_enrollment_id_index');
                $table->dropForeign(['enrollment_id']);
                $table->dropColumn(['enrollment_id', 'attendance_date', 'remarks']);
            }
        });

        Schema::table('timetables', function (Blueprint $table): void {
            if (Schema::hasColumn('timetables', 'section_id')) {
                $table->dropIndex('timetables_section_id_index');
                $table->dropForeign(['section_id']);
                $table->dropColumn(['section_id', 'room_no']);
            }
        });

        Schema::table('announcements', function (Blueprint $table): void {
            if (Schema::hasColumn('announcements', 'posted_by')) {
                $table->dropForeign(['posted_by']);
            }

            $dropColumns = [];
            foreach (['message', 'audience', 'posted_by'] as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            $dropColumns = [];
            foreach ([
                'admission_no',
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'phone',
                'email',
                'address',
                'admission_date',
                'status',
            ] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'subject_code')) {
                $table->dropUnique('subjects_subject_code_unique');
            }

            $dropColumns = [];
            foreach (['subject_code', 'subject_name', 'is_optional'] as $column) {
                if (Schema::hasColumn('subjects', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'class_name')) {
                $table->dropColumn('class_name');
            }
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropForeign(['role_id']);
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique('users_username_unique');
            }

            $dropColumns = [];
            foreach (['username', 'role_id', 'is_active'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::dropIfExists('roles');
    }

    private function createRolesTable(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->increments('role_id');
                $table->string('role_name', 30)->unique();
            });
        }

        DB::table('roles')->insertOrIgnore([
            ['role_name' => 'Super Admin'],
            ['role_name' => 'Admin'],
            ['role_name' => 'Teacher'],
            ['role_name' => 'Student'],
            ['role_name' => 'Guardian'],
        ]);
    }

    private function upgradeUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 50)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('users', 'role_id')) {
                $table->unsignedInteger('role_id')->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('active');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->foreign('role_id')->references('role_id')->on('roles')->nullOnDelete();
                $table->index('role_id', 'users_role_id_index');
            }
        });

        $users = DB::table('users')->select(['id', 'email', 'name', 'role', 'active', 'is_active'])->get();
        $roleMap = DB::table('roles')->pluck('role_id', 'role_name')->all();

        foreach ($users as $user) {
            $base = '';
            if (is_string($user->email) && $user->email !== '') {
                $base = Str::before($user->email, '@');
            } elseif (is_string($user->name) && $user->name !== '') {
                $base = Str::slug($user->name, '_');
            }

            $base = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string) $base));
            $base = trim((string) $base, '_');
            if ($base === '') {
                $base = 'user';
            }
            $username = mb_substr($base, 0, 38).'_'.$user->id;

            $roleName = match ((string) $user->role) {
                'super-admin' => 'Super Admin',
                'admin' => 'Admin',
                'teacher' => 'Teacher',
                'student' => 'Student',
                'parent', 'guardian' => 'Guardian',
                default => null,
            };

            DB::table('users')->where('id', (int) $user->id)->update([
                'username' => $username,
                'is_active' => $user->active ?? true,
                'role_id' => $roleName !== null ? ($roleMap[$roleName] ?? null) : null,
            ]);
        }
    }

    private function createAcademicCoreTables(): void
    {
        if (! Schema::hasTable('academic_years')) {
            Schema::create('academic_years', function (Blueprint $table): void {
                $table->increments('academic_year_id');
                $table->string('year_name', 20)->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_current')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('terms')) {
            Schema::create('terms', function (Blueprint $table): void {
                $table->increments('term_id');
                $table->unsignedInteger('academic_year_id');
                $table->string('term_name', 30);
                $table->date('start_date');
                $table->date('end_date');
                $table->timestamps();

                $table->foreign('academic_year_id')->references('academic_year_id')->on('academic_years')->cascadeOnDelete();
                $table->unique(['academic_year_id', 'term_name'], 'terms_year_term_name_unique');
            });
        }

        if (! Schema::hasTable('guardians')) {
            Schema::create('guardians', function (Blueprint $table): void {
                $table->bigIncrements('guardian_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('first_name', 50);
                $table->string('last_name', 50);
                $table->string('relationship_to_student', 30);
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_guardians')) {
            Schema::create('student_guardians', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('guardian_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->primary(['student_id', 'guardian_id']);
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->foreign('guardian_id')->references('guardian_id')->on('guardians')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table): void {
                $table->bigIncrements('teacher_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('employee_no', 30)->unique();
                $table->string('first_name', 50);
                $table->string('last_name', 50);
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('specialization', 100)->nullable();
                $table->date('hire_date');
                $table->enum('status', ['Active', 'On Leave', 'Inactive'])->default('Active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sections')) {
            Schema::create('sections', function (Blueprint $table): void {
                $table->increments('section_id');
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->unsignedInteger('academic_year_id');
                $table->string('section_name', 20);
                $table->unsignedBigInteger('class_teacher_id')->nullable();
                $table->string('room_no', 20)->nullable();
                $table->timestamps();

                $table->unique(['class_id', 'academic_year_id', 'section_name'], 'sections_class_year_name_unique');
                $table->foreign('academic_year_id')->references('academic_year_id')->on('academic_years')->cascadeOnDelete();
                $table->foreign('class_teacher_id')->references('teacher_id')->on('teachers')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('teacher_assignments')) {
            Schema::create('teacher_assignments', function (Blueprint $table): void {
                $table->bigIncrements('assignment_id');
                $table->unsignedBigInteger('teacher_id');
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->unsignedInteger('section_id');
                $table->timestamps();

                $table->unique(['teacher_id', 'subject_id', 'section_id'], 'teacher_assignments_unique');
                $table->foreign('teacher_id')->references('teacher_id')->on('teachers')->cascadeOnDelete();
                $table->foreign('section_id')->references('section_id')->on('sections')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('enrollments')) {
            Schema::create('enrollments', function (Blueprint $table): void {
                $table->bigIncrements('enrollment_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedInteger('academic_year_id');
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->unsignedInteger('section_id');
                $table->string('roll_no', 20)->nullable();
                $table->date('enrollment_date');
                $table->enum('status', ['Enrolled', 'Promoted', 'Completed', 'Dropped'])->default('Enrolled');
                $table->timestamps();

                $table->unique(['student_id', 'academic_year_id'], 'enrollments_student_year_unique');
                $table->unique(['section_id', 'roll_no'], 'enrollments_section_roll_no_unique');
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->foreign('academic_year_id')->references('academic_year_id')->on('academic_years')->cascadeOnDelete();
                $table->foreign('section_id')->references('section_id')->on('sections')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('exams')) {
            Schema::create('exams', function (Blueprint $table): void {
                $table->increments('exam_id');
                $table->unsignedInteger('term_id');
                $table->string('exam_name', 100);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->foreign('term_id')->references('term_id')->on('terms')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('exam_subjects')) {
            Schema::create('exam_subjects', function (Blueprint $table): void {
                $table->bigIncrements('exam_subject_id');
                $table->unsignedInteger('exam_id');
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->decimal('max_marks', 5, 2)->default(100.00);
                $table->decimal('pass_marks', 5, 2)->default(40.00);
                $table->timestamps();

                $table->unique(['exam_id', 'subject_id'], 'exam_subjects_exam_subject_unique');
                $table->foreign('exam_id')->references('exam_id')->on('exams')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('marks')) {
            Schema::create('marks', function (Blueprint $table): void {
                $table->bigIncrements('mark_id');
                $table->unsignedBigInteger('exam_subject_id');
                $table->unsignedBigInteger('enrollment_id');
                $table->decimal('obtained_marks', 5, 2);
                $table->string('grade_letter', 5)->nullable();
                $table->string('remarks', 255)->nullable();
                $table->timestamps();

                $table->unique(['exam_subject_id', 'enrollment_id'], 'marks_exam_subject_enrollment_unique');
                $table->foreign('exam_subject_id')->references('exam_subject_id')->on('exam_subjects')->cascadeOnDelete();
                $table->foreign('enrollment_id')->references('enrollment_id')->on('enrollments')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fee_types')) {
            Schema::create('fee_types', function (Blueprint $table): void {
                $table->increments('fee_type_id');
                $table->string('fee_name', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->decimal('default_amount', 10, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_fees')) {
            Schema::create('student_fees', function (Blueprint $table): void {
                $table->bigIncrements('student_fee_id');
                $table->unsignedBigInteger('enrollment_id');
                $table->unsignedInteger('fee_type_id');
                $table->decimal('amount_due', 10, 2);
                $table->date('due_date');
                $table->enum('status', ['Unpaid', 'Partially Paid', 'Paid', 'Waived'])->default('Unpaid');
                $table->timestamps();

                $table->unique(['enrollment_id', 'fee_type_id', 'due_date'], 'student_fees_unique');
                $table->foreign('enrollment_id')->references('enrollment_id')->on('enrollments')->cascadeOnDelete();
                $table->foreign('fee_type_id')->references('fee_type_id')->on('fee_types')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->bigIncrements('payment_id');
                $table->unsignedBigInteger('student_fee_id');
                $table->decimal('amount_paid', 10, 2);
                $table->date('payment_date');
                $table->enum('payment_method', ['Cash', 'Bank', 'Card', 'Mobile Money']);
                $table->string('reference_no', 50)->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('student_fee_id')->references('student_fee_id')->on('student_fees')->cascadeOnDelete();
            });
        }

        $this->seedDefaultAcademicYear();
    }

    private function upgradeCoreDomainTables(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'class_name')) {
                $table->string('class_name', 50)->nullable()->after('name');
            }
        });
        DB::table('classes')->whereNull('class_name')->update(['class_name' => DB::raw('name')]);

        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'subject_code')) {
                $table->string('subject_code', 20)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('subjects', 'subject_name')) {
                $table->string('subject_name', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('subjects', 'is_optional')) {
                $table->boolean('is_optional')->default(false)->after('subject_name');
            }
        });
        DB::table('subjects')->whereNull('subject_name')->update(['subject_name' => DB::raw('name')]);

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'admission_no')) {
                $table->string('admission_no', 30)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('students', 'first_name')) {
                $table->string('first_name', 50)->nullable()->after('admission_no');
            }
            if (! Schema::hasColumn('students', 'last_name')) {
                $table->string('last_name', 50)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('students', 'gender')) {
                $table->string('gender', 10)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('students', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('students', 'phone')) {
                $table->string('phone', 20)->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('students', 'email')) {
                $table->string('email', 100)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('students', 'address')) {
                $table->text('address')->nullable()->after('email');
            }
            if (! Schema::hasColumn('students', 'admission_date')) {
                $table->date('admission_date')->nullable()->after('address');
            }
            if (! Schema::hasColumn('students', 'status')) {
                $table->string('status', 20)->default('Active')->after('admission_date');
            }
        });

        $students = DB::table('students')->select(['id', 'user_id', 'admission_no'])->get();
        foreach ($students as $student) {
            $user = DB::table('users')->where('id', $student->user_id)->first();
            if (! $user) {
                continue;
            }

            $name = trim((string) ($user->name ?? ''));
            $parts = preg_split('/\s+/', $name) ?: [];
            $firstName = $parts[0] ?? null;
            $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

            DB::table('students')->where('id', $student->id)->update([
                'admission_no' => $student->admission_no ?: 'ADM-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => ucfirst((string) ($user->gender ?? 'Other')),
                'date_of_birth' => $user->dob,
                'phone' => $user->phone,
                'email' => $user->email,
                'address' => $user->address,
                'admission_date' => Carbon::now()->toDateString(),
                'status' => 'Active',
            ]);
        }

        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'message')) {
                $table->text('message')->nullable()->after('content');
            }
            if (! Schema::hasColumn('announcements', 'audience')) {
                $table->string('audience', 20)->default('All')->after('title');
            }
            if (! Schema::hasColumn('announcements', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('class_id')->constrained('users')->nullOnDelete();
            }
        });
        DB::table('announcements')->whereNull('message')->update(['message' => DB::raw('content')]);

        Schema::table('timetables', function (Blueprint $table): void {
            if (! Schema::hasColumn('timetables', 'section_id')) {
                $table->unsignedInteger('section_id')->nullable()->after('class_id');
                $table->foreign('section_id')->references('section_id')->on('sections')->nullOnDelete();
                $table->index('section_id', 'timetables_section_id_index');
            }
            if (! Schema::hasColumn('timetables', 'room_no')) {
                $table->string('room_no', 20)->nullable()->after('time_end');
            }
        });

        Schema::table('attendance', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance', 'enrollment_id')) {
                $table->unsignedBigInteger('enrollment_id')->nullable()->after('id');
                $table->foreign('enrollment_id')->references('enrollment_id')->on('enrollments')->nullOnDelete();
                $table->index('enrollment_id', 'attendance_enrollment_id_index');
            }
            if (! Schema::hasColumn('attendance', 'attendance_date')) {
                $table->date('attendance_date')->nullable()->after('date');
            }
            if (! Schema::hasColumn('attendance', 'remarks')) {
                $table->string('remarks', 255)->nullable()->after('status');
            }
        });
        DB::table('attendance')->whereNull('attendance_date')->update(['attendance_date' => DB::raw('date')]);
        Schema::table('attendance', function (Blueprint $table): void {
            $table->unique(['enrollment_id', 'attendance_date'], 'attendance_enrollment_date_unique');
        });
    }

    private function seedDefaultAcademicYear(): void
    {
        $exists = DB::table('academic_years')->where('is_current', true)->exists();
        if ($exists) {
            return;
        }

        $now = Carbon::now();
        $startYear = (int) $now->format('Y');
        $endYear = $startYear + 1;

        DB::table('academic_years')->insert([
            'year_name' => $startYear.'/'.$endYear,
            'start_date' => Carbon::create($startYear, 9, 1)->toDateString(),
            'end_date' => Carbon::create($endYear, 8, 31)->toDateString(),
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
