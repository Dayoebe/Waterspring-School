<div class="dashboard-overview space-y-6 pb-4">
    @if ($loading)
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-slate-600">Loading dashboard data...</p>
        </div>
    @else
        @php
            $user = auth()->user();

            $roleTheme = match (true) {
                $isSuperAdmin => [
                    'badge' => 'bg-slate-50 text-slate-950',
                    'panel' => 'border-slate-200 bg-slate-50',
                    'soft' => 'bg-slate-50 text-sky-700',
                ],
                $isStaff => [
                    'badge' => 'bg-slate-50 text-slate-950',
                    'panel' => 'border-slate-200 bg-slate-50',
                    'soft' => 'bg-slate-50 text-sky-700',
                ],
                $isStudent => [
                    'badge' => 'bg-slate-50 text-slate-950',
                    'panel' => 'border-slate-200 bg-slate-50',
                    'soft' => 'bg-slate-50 text-sky-700',
                ],
                $isParent => [
                    'badge' => 'bg-slate-50 text-slate-950',
                    'panel' => 'border-slate-200 bg-slate-50',
                    'soft' => 'bg-slate-50 text-sky-700',
                ],
                default => [
                    'badge' => 'bg-slate-50 text-slate-950',
                    'panel' => 'border-slate-200 bg-slate-50',
                    'soft' => 'bg-slate-50 text-slate-900',
                ],
            };

            $roleSummary = match (true) {
                $isSuperAdmin => 'You are controlling the school-wide setup, operations, and reporting from one place.',
                $isRestrictedTeacher => 'Your dashboard is limited to your assigned classes, subjects, and core teacher workflows only.',
                $isTeacher => 'Your dashboard shows the classes and subjects assigned to you, together with your teacher tools.',
                $isStaff => 'Your dashboard is focused on the classes, records, and workflows you are allowed to manage.',
                $isStudent => 'Your dashboard is focused on your current class work, results, and exam access only.',
                $isParent => 'Your dashboard keeps your linked children, results, and welfare information in one place.',
                default => 'Your dashboard shows the tools and information available to this account.',
            };

            $pulseCards = $isStaff ? array_values(array_filter([
                [
                    'label' => 'Active Students',
                    'value' => $snapshot['active_students'] ?? 0,
                    'helper' => 'Currently enrolled this academic year',
                    'icon' => 'fas fa-user-graduate',
                ],
                [
                    'label' => 'Attendance Today',
                    'value' => isset($snapshot['attendance_rate']) ? $snapshot['attendance_rate'] . '%' : 'Not taken',
                    'helper' => 'Present and late students recorded today',
                    'icon' => 'fas fa-user-check',
                ],
                [
                    'label' => 'Pending Admissions',
                    'value' => $snapshot['pending_admissions'] ?? 0,
                    'helper' => 'Applications awaiting a decision',
                    'icon' => 'fas fa-user-plus',
                ],
                [
                    'label' => 'Overdue Invoices',
                    'value' => $snapshot['overdue_invoices'] ?? 0,
                    'helper' => 'Invoices with an outstanding balance',
                    'icon' => 'fas fa-receipt',
                ],
                [
                    'label' => 'Result Approval',
                    'value' => isset($snapshot['result_completion']) ? $snapshot['result_completion'] . '%' : 'Not started',
                    'helper' => 'Current-term entries approved',
                    'icon' => 'fas fa-chart-line',
                ],
            ], fn ($card) => !in_array($card['label'], ['Pending Admissions', 'Overdue Invoices'], true)
                || ($card['label'] === 'Pending Admissions' && $user->can('read admission registration'))
                || ($card['label'] === 'Overdue Invoices' && $user->can('read fee invoice')))) : array_values(array_filter([
                [
                    'label' => 'Active Notices',
                    'value' => $snapshot['active_notices'] ?? 0,
                    'helper' => 'Current school announcements',
                    'icon' => 'fas fa-bullhorn',
                ],
                [
                    'label' => 'Ongoing Exams',
                    'value' => $snapshot['ongoing_exams'] ?? 0,
                    'helper' => 'Exam windows active right now',
                    'icon' => 'fas fa-hourglass-half',
                ],
                [
                    'label' => 'Upcoming Exams',
                    'value' => $snapshot['upcoming_exams'] ?? 0,
                    'helper' => 'Scheduled next in the exam calendar',
                    'icon' => 'fas fa-calendar-alt',
                ],
                [
                    'label' => 'Published Exams',
                    'value' => $snapshot['published_exams'] ?? 0,
                    'helper' => 'Exams with visible results',
                    'icon' => 'fas fa-check-circle',
                ],
                [
                    'label' => 'Term Results',
                    'value' => $snapshot['term_results'] ?? 0,
                    'helper' => 'Current-term result entries',
                    'icon' => 'fas fa-chart-line',
                ],
            ], fn ($card) => !($isStudent || $isParent) || $card['label'] !== 'Term Results' || ($card['value'] ?? 0) > 0));

            $staffMetrics = collect([
                [
                    'label' => 'Schools',
                    'value' => $stats['schools'] ?? 0,
                    'route' => 'schools.index',
                    'visible' => $isSuperAdmin,
                    'permissions' => ['read school', 'create school', 'manage school settings'],
                ],
                [
                    'label' => 'Active Students',
                    'value' => $stats['active_students'] ?? 0,
                    'route' => 'students.index',
                    'visible' => $isStaff,
                    'permissions' => ['read student'],
                ],
                [
                    'label' => 'Inactive Students',
                    'value' => $stats['inactive_students'] ?? 0,
                    'route' => 'students.index',
                    'visible' => $isStaff,
                    'permissions' => ['read student'],
                ],
                [
                    'label' => 'Teachers',
                    'value' => $stats['teachers'] ?? 0,
                    'route' => 'teachers.index',
                    'visible' => $isStaff,
                    'permissions' => ['read teacher'],
                ],
                [
                    'label' => 'Parents',
                    'value' => $stats['parents'] ?? 0,
                    'route' => 'parents.index',
                    'visible' => $isStaff,
                    'permissions' => ['read parent'],
                ],
                [
                    'label' => 'Subjects',
                    'value' => $stats['subjects'] ?? 0,
                    'route' => 'subjects.index',
                    'visible' => $isStaff,
                    'permissions' => ['read subject'],
                ],
                [
                    'label' => 'Exams',
                    'value' => $stats['total_exams'] ?? 0,
                    'route' => 'exams.index',
                    'visible' => $isStaff,
                    'permissions' => ['read exam', 'read exam record'],
                ],
                [
                    'label' => 'Notices',
                    'value' => $stats['total_notices'] ?? 0,
                    'route' => 'notices.index',
                    'visible' => $isStaff,
                    'permissions' => ['read notice', 'create notice', 'update notice'],
                ],
                [
                    'label' => 'Graduated',
                    'value' => $stats['graduated_students'] ?? 0,
                    'route' => 'students.graduations',
                    'visible' => $isStaff,
                    'permissions' => ['read student'],
                ],
            ])->filter(function ($metric) use ($user) {
                if (($metric['visible'] ?? true) === false) {
                    return false;
                }

                if (!empty($metric['permissions'])) {
                    foreach ($metric['permissions'] as $permission) {
                        if (is_string($permission) && $user->can($permission)) {
                            return true;
                        }
                    }

                    return false;
                }

                return true;
            })->values();

            $studentHighlights = [
                ['label' => 'Admission No.', 'value' => $studentPanel['admission_number'] ?? 'N/A'],
                ['label' => 'Class', 'value' => $studentPanel['class_name'] ?? 'Not assigned'],
                ['label' => 'Section', 'value' => $studentPanel['section_name'] ?? 'Not assigned'],
                ['label' => 'Subjects', 'value' => $studentPanel['subject_count'] ?? 0],
                ['label' => 'Result Entries', 'value' => $studentPanel['result_count'] ?? 0],
                ['label' => 'Approved Results', 'value' => $studentPanel['approved_result_count'] ?? 0],
                ['label' => 'Average Score', 'value' => $studentPanel['average_score'] ?? '0.0'],
            ];

            $staffMetricTones = [
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
            ];

            $studentHighlightTones = [
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
            ];

            $parentChildTones = [
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
            ];

            $teacherManagedClasses = collect($teacherPanel['managed_classes'] ?? []);
            $teacherSubjectAssignments = collect($teacherPanel['subject_assignments'] ?? []);
            $teacherFocusItems = $teacherPanel['focus_items'] ?? [];
            $teacherHighlights = [
                ['label' => 'Managed Classes', 'value' => $teacherPanel['class_teacher_classes'] ?? 0],
                ['label' => 'Teaching Classes', 'value' => $teacherPanel['teaching_classes'] ?? 0],
                ['label' => 'Assigned Subjects', 'value' => $teacherPanel['assigned_subjects'] ?? 0],
                ['label' => 'Subject-Class Loads', 'value' => $teacherPanel['teaching_assignments'] ?? 0],
                ['label' => 'Managed Students', 'value' => $teacherPanel['managed_students'] ?? 0],
                ['label' => 'Teacher Tools', 'value' => $teacherPanel['teacher_tools'] ?? 0],
            ];

            $teacherHighlightTones = [
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
                'bg-slate-50 text-slate-950',
                'bg-slate-50 text-slate-700',
            ];
        @endphp

        <section class="dashboard-welcome">
            <div>
                <span class="dashboard-badge">{{ $roleLabel }}</span>
                <h2>Welcome back, {{ $user->name }}</h2>
                <p>{{ $roleSummary }}</p>
            </div>
            <dl class="dashboard-context-grid">
                <div><dt>Academic year</dt><dd>{{ $academicContext['academic_year'] ?? 'Not set' }}</dd></div>
                <div><dt>Term</dt><dd>{{ $academicContext['semester'] ?? 'Not set' }}</dd></div>
                <div><dt>Today</dt><dd>{{ $academicContext['today'] ?? now('Africa/Lagos')->format('D, M j, Y · g:i:s A') }}</dd></div>
            </dl>
        </section>
        <div class="dashboard-metric-grid">
            @foreach ($pulseCards as $pulseCard)
                <article class="dashboard-metric">
                    <div class="dashboard-metric-label"><span>{{ $pulseCard['label'] }}</span><i class="{{ $pulseCard['icon'] }}" aria-hidden="true"></i></div>
                    <p class="dashboard-metric-value">{{ $pulseCard['value'] }}</p>
                    <p class="dashboard-metric-help">{{ $pulseCard['helper'] }}</p>
                </article>
            @endforeach
        </div>

        @if ($isStaff && $attentionItems !== [])
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Action centre</p>
                        <h3 class="mt-2 text-2xl font-bold text-slate-900">Needs attention</h3>
                        <p class="mt-2 text-sm text-slate-600">Open records that may need a decision or follow-up.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ count($attentionItems) }} active
                    </span>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($attentionItems as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                            class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-sky-300 hover:bg-sky-50">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-sky-700 shadow-sm">
                                <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-3">
                                    <strong class="text-sm text-slate-900">{{ $item['title'] }}</strong>
                                    <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">{{ $item['count'] }}</span>
                                </span>
                                <span class="mt-1 block text-xs leading-5 text-slate-600">{{ $item['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($isTeacher && $teacherPanel !== [])
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Teacher Overview</p>
                        <h3 class="mt-2 text-3xl font-black text-slate-900">Your assigned classes and subjects</h3>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                            View the classes you manage, the subjects you teach, and the tools available to your account.
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm font-medium text-sky-700">
                        {{ $teacherManagedClasses->count() }} managed class{{ $teacherManagedClasses->count() === 1 ? '' : 'es' }} •
                        {{ $teacherSubjectAssignments->count() }} teaching assignment{{ $teacherSubjectAssignments->count() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($teacherHighlights as $highlight)
                        @php($teacherTone = $teacherHighlightTones[$loop->index % count($teacherHighlightTones)])
                        <div class="rounded-xl px-4 py-4 shadow-md {{ $teacherTone }}">
                            <p class="text-[11px] font-semibold uppercase tracking-wide opacity-70">{{ $highlight['label'] }}</p>
                            <p class="mt-2 text-2xl font-black">{{ $highlight['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($teacherFocusItems !== [])
                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick Actions</p>
                                <h4 class="mt-2 text-xl font-bold text-slate-900">Available teacher actions</h4>
                            </div>
                            <p class="text-sm text-slate-500">Only actions available to your account are shown.</p>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                            @foreach ($teacherFocusItems as $item)
                                <a
                                    href="{{ route($item['route']) }}"
                                    class="dashboard-action-card group rounded-xl p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl dashboard-action-tone"
                                    wire:navigate
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 shadow-sm">
                                            <i class="{{ $item['icon'] }} text-lg"></i>
                                        </div>
                                        <span class="text-xs font-semibold uppercase tracking-wide opacity-75">{{ $item['cta'] }}</span>
                                    </div>
                                    <h5 class="mt-5 text-lg font-semibold">{{ $item['title'] }}</h5>
                                    <p class="mt-2 text-sm leading-6 opacity-85">{{ $item['description'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6 grid gap-5 xl:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Managed Classes</p>
                                <h4 class="mt-2 text-xl font-bold text-slate-900">Classes you manage</h4>
                            </div>
                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                {{ $teacherManagedClasses->count() }}
                            </span>
                        </div>

                        @if ($teacherManagedClasses->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach ($teacherManagedClasses as $class)
                                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-lg font-semibold text-slate-900">{{ $class['name'] }}</p>
                                                <p class="mt-1 text-sm text-slate-500">{{ $class['class_group'] }}</p>
                                            </div>
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-700">
                                                Class Teacher
                                            </span>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                                                {{ $class['student_count'] }} students
                                            </span>
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                                {{ $class['section_count'] }} section{{ $class['section_count'] === 1 ? '' : 's' }}
                                            </span>
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                                {{ $class['subject_count'] }} class subject{{ $class['subject_count'] === 1 ? '' : 's' }}
                                            </span>
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                                {{ $class['teaching_subject_count'] }} of your subject{{ $class['teaching_subject_count'] === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-600">
                                No class teacher assignment was found for this account.
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Teaching Load</p>
                                <h4 class="mt-2 text-xl font-bold text-slate-900">Subjects and classes you teach</h4>
                            </div>
                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                {{ $teacherSubjectAssignments->count() }}
                            </span>
                        </div>

                        @if ($teacherSubjectAssignments->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach ($teacherSubjectAssignments as $assignment)
                                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-lg font-semibold text-slate-900">{{ $assignment['subject_name'] }}</p>
                                                <p class="mt-1 text-sm text-slate-500">
                                                    {{ $assignment['class_name'] }}{{ !empty($assignment['class_group']) ? ' • ' . $assignment['class_group'] : '' }}
                                                </p>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-700">
                                                    {{ $assignment['assignment_scope'] }}
                                                </span>
                                                @if ($assignment['is_managed_class'])
                                                    <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-700">
                                                        Class teacher
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                                                {{ $assignment['student_count'] }} students in class
                                            </span>
                                            @if (!empty($assignment['subject_short_name']))
                                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                                    {{ $assignment['subject_short_name'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-600">
                                No subject assignment was found for this account.
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200/80 pb-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick access</p>
                        <h3 class="mt-2 text-2xl font-bold text-slate-900">Your tools</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Your most useful actions for today. Use the sidebar to open every module.
                        </p>
                    </div>
                    <div class="rounded-2xl {{ $roleTheme['panel'] }} px-4 py-3 text-sm font-medium text-slate-700">
                        Showing {{ $availableActionCount > 0 ? (($quickActionPage - 1) * $quickActionsPerPage) + 1 : 0 }}–{{ min($quickActionPage * $quickActionsPerPage, $availableActionCount) }} of {{ $availableActionCount }}
                    </div>
                </div>

                @if ($quickActions !== [])
                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($quickActions as $action)
                            <a
                                href="{{ route($action['route']) }}"
                                class="dashboard-action-card group rounded-xl bg-slate-50 p-5 text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl"
                                wire:navigate
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-sky-700 shadow-sm">
                                        <i class="{{ $action['icon'] }} text-lg"></i>
                                    </div>
                                    <i class="fas fa-arrow-right text-sm text-slate-500 transition group-hover:translate-x-0.5 group-hover:text-sky-700"></i>
                                </div>
                                <h4 class="mt-5 text-lg font-semibold">{{ $action['title'] }}</h4>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $action['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                        No dashboard actions are available for this account yet.
                    </div>
                @endif

                @if ($quickActionPageCount > 1)
                    <nav class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Your tools pages">
                        <p class="text-xs font-medium text-slate-500">
                            Page {{ $quickActionPage }} of {{ $quickActionPageCount }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="previousQuickActionPage" wire:loading.attr="disabled" wire:target="previousQuickActionPage,nextQuickActionPage,setQuickActionPage" @disabled($quickActionPage <= 1)
                                class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-40">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i> Previous
                            </button>
                            @for ($page = 1; $page <= $quickActionPageCount; $page++)
                                <button type="button" wire:click="setQuickActionPage({{ $page }})" wire:loading.attr="disabled" wire:target="previousQuickActionPage,nextQuickActionPage,setQuickActionPage"
                                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-xs font-bold transition {{ $page === $quickActionPage ? 'border-sky-700 bg-sky-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 hover:text-sky-700' }}"
                                    aria-label="Open tools page {{ $page }}" aria-current="{{ $page === $quickActionPage ? 'page' : 'false' }}">
                                    {{ $page }}
                                </button>
                            @endfor
                            <button type="button" wire:click="nextQuickActionPage" wire:loading.attr="disabled" wire:target="previousQuickActionPage,nextQuickActionPage,setQuickActionPage" @disabled($quickActionPage >= $quickActionPageCount)
                                class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-40">
                                Next <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </nav>
                @endif

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account</p>
                            <h4 class="mt-1 text-lg font-bold text-slate-900">Current context</h4>
                        </div>
                        <p class="text-xs text-slate-500">The school period currently applied to your tools.</p>
                    </div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">School</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $academicContext['school_name'] ?? config('app.name') }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Academic year</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $academicContext['academic_year'] ?? 'Not set' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Term</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $academicContext['semester'] ?? 'Not set' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div class="space-y-6">
                @if ($isStaff && !$isRestrictedTeacher && $staffMetrics->isNotEmpty())
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">School Snapshot</p>
                                <h3 class="mt-2 text-2xl font-bold text-slate-900">Operational totals</h3>
                            </div>
                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                Staff
                            </span>
                        </div>

                        <div class="mt-5 space-y-3">
                            @foreach ($staffMetrics as $metric)
                                @php($metricTone = $staffMetricTones[$loop->index % count($staffMetricTones)])
                                <a
                                    href="{{ route($metric['route']) }}"
                                    class="flex items-center justify-between rounded-2xl px-4 py-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg {{ $metricTone }}"
                                    wire:navigate
                                >
                                    <div>
                                        <p class="text-sm font-semibold">{{ $metric['label'] }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-wide opacity-75">Current count</p>
                                    </div>
                                    <span class="text-2xl font-black">{{ $metric['value'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($isStudent && $studentPanel !== [])
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student Overview</p>
                                <h3 class="mt-2 text-2xl font-bold text-slate-900">Your current standing</h3>
                            </div>
                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                Student
                            </span>
                        </div>

                        @if(!($studentPanel['result_published'] ?? false))
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-sky-700">
                                Your current term result is still being prepared. Scores will appear here after the school publishes the result.
                            </div>
                        @endif

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($studentHighlights as $highlight)
                                @php($studentTone = $studentHighlightTones[$loop->index % count($studentHighlightTones)])
                                <div class="rounded-2xl px-4 py-4 shadow-md {{ $studentTone }}">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide opacity-70">{{ $highlight['label'] }}</p>
                                    <p class="mt-2 text-xl font-bold">{{ $highlight['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if ($user->can('view result'))
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3">
                                    <h4 class="text-sm font-semibold text-slate-900">Result Period</h4>
                                    <p class="mt-1 text-sm text-slate-600">Pick the academic year and term before opening your result.</p>
                                </div>

                                <livewire:result.academic-period-selector />
                            </div>
                        @endif
                    </section>
                @endif

                @if ($isParent && $parentPanel !== [])
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Parent Overview</p>
                                <h3 class="mt-2 text-2xl font-bold text-slate-900">Linked children</h3>
                            </div>
                            <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                {{ $parentPanel['total_children'] ?? 0 }} Child(ren)
                            </span>
                        </div>

                        @if (($parentPanel['total_children'] ?? 0) > 0)
                            <div class="mt-5 space-y-3">
                                @foreach ($parentPanel['children'] as $child)
                                    @php($childTone = $parentChildTones[$loop->index % count($parentChildTones)])
                                    <div class="rounded-2xl px-4 py-4 shadow-md {{ $childTone }}">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-base font-semibold">{{ $child['name'] }}</p>
                                                <p class="mt-1 text-sm opacity-80">Admission: {{ $child['admission_number'] }}</p>
                                            </div>
                                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
                                                {{ $child['class_name'] }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm opacity-80">Section: {{ $child['section_name'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if (($parentPanel['hidden_count'] ?? 0) > 0)
                                <p class="mt-3 text-sm text-slate-500">
                                    +{{ $parentPanel['hidden_count'] }} more child(ren) linked to this account.
                                </p>
                            @endif
                        @else
                            <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-600">
                                No student records are currently linked to this parent account.
                            </div>
                        @endif

                        @if ($user->can('view result'))
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3">
                                    <h4 class="text-sm font-semibold text-slate-900">Result Period</h4>
                                    <p class="mt-1 text-sm text-slate-600">Choose the academic year and term before opening a child result.</p>
                                </div>

                                <livewire:result.academic-period-selector />
                            </div>
                        @endif
                    </section>
                @endif

            </div>
        </div>

        @if ($featureGroups !== [])
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Platform directory</p>
                        <h3 class="mt-2 text-2xl font-bold text-slate-900">Explore your school management tools</h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Every module available to your account is organised below. Open a workspace directly without searching through the menu.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-800">
                        {{ $availableActionCount }} available features
                    </span>
                </div>

                <div class="mt-6 space-y-7">
                    @foreach ($featureGroups as $group => $actions)
                        <div>
                            <div class="mb-3 flex items-center gap-3">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ $group }}</h4>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ count($actions) }}</span>
                                <span class="h-px flex-1 bg-slate-200"></span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($actions as $action)
                                    <a href="{{ route($action['route']) }}" wire:navigate
                                        class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-50 hover:shadow-md">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-sky-700 shadow-sm">
                                            <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-3">
                                                <strong class="text-sm text-slate-900">{{ $action['title'] }}</strong>
                                                <i class="fas fa-arrow-right text-xs text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-700" aria-hidden="true"></i>
                                            </span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-600">{{ $action['description'] }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
