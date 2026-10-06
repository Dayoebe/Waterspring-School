<?php

namespace App\Livewire\Dashboard;

use App\Models\AdmissionRegistration;
use App\Models\AttendanceRecord;
use App\Models\ClassGroup;
use App\Models\ContactMessage;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\MyClass;
use App\Models\Notice;
use App\Models\Result;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Models\Subject;
use App\Models\User;
use App\Support\ResultPublicationStatus;
use App\Support\TeacherResponsibilityBuilder;
use App\Traits\RestrictsTeacherPortalAccess;
use App\Traits\RestrictsTeacherResultViewing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class DashboardStats extends Component
{
    use RestrictsTeacherPortalAccess;
    use RestrictsTeacherResultViewing;

    public $stats = [];

    public $snapshot = [];

    public $quickActions = [];

    public $allQuickActions = [];

    public $quickActionPage = 1;

    public $quickActionsPerPage = 7;

    public $quickActionPageCount = 1;

    public $featureGroups = [];

    public $attentionItems = [];

    public $availableActionCount = 0;

    public $teacherPanel = [];

    public $studentPanel = [];

    public $parentPanel = [];

    public $academicContext = [];

    public $roleLabel = 'User';

    public $loading = true;

    public $isStaff = false;

    public $isTeacher = false;

    public $isRestrictedTeacher = false;

    public $isStudent = false;

    public $isParent = false;

    public $isSuperAdmin = false;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->loading = false;

            return;
        }

        $user->loadMissing([
            'school.academicYear',
            'school.semester',
            'studentRecord',
            'children.studentRecord',
        ]);

        $this->isSuperAdmin = $user->hasAnyRole(['super-admin', 'super_admin']);
        $this->isTeacher = $user->hasRole('teacher');
        $this->isRestrictedTeacher = $this->isRestrictedTeacherPortalUser($user);
        $this->isStudent = $user->hasRole('student');
        $this->isParent = $user->hasRole('parent');
        $this->isStaff = $user->hasAnyRole(['super-admin', 'super_admin', 'principal', 'admin', 'teacher']);

        $this->roleLabel = $this->resolveRoleLabel($user);
        $this->loadAcademicContext($user);
        $this->loadSnapshot($user);
        $this->loadQuickActions($user);
        $this->loadAttentionItems($user);

        if ($this->isStaff && ! $this->isRestrictedTeacher) {
            $this->loadStats($user);
        }

        if ($this->isTeacher) {
            $this->loadTeacherPanel($user);
        }

        if ($this->isStudent) {
            $this->loadStudentPanel($user);
        }

        if ($this->isParent) {
            $this->loadParentPanel($user);
        }

        $this->loading = false;
    }

    private function resolveRoleLabel(User $user): string
    {
        if ($this->isSuperAdmin) {
            return 'Super Admin';
        }

        $roleMap = [
            'principal' => 'Principal',
            'admin' => 'Admin',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent' => 'Parent',
            'user' => 'User',
        ];

        foreach ($roleMap as $role => $label) {
            if ($user->hasRole($role)) {
                return $label;
            }
        }

        return 'User';
    }

    private function loadAcademicContext(User $user): void
    {
        $this->academicContext = [
            'school_name' => $user->school?->name ?? config('app.name'),
            'academic_year' => $user->school?->academicYear?->name ?? 'Not set',
            'semester' => $user->school?->semester?->name ?? 'Not set',
            'today' => Carbon::now('Africa/Lagos')->format('D, M j, Y · g:i A'),
        ];
    }

    private function loadSnapshot(User $user): void
    {
        $schoolId = $user->school_id;
        $today = Carbon::today();
        $activeStudentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $schoolId,
            $user->school?->academic_year_id
        );

        $activeNotices = 0;
        $ongoingExams = 0;
        $upcomingExams = 0;
        $publishedExams = 0;
        $termResults = 0;

        if ($schoolId) {
            $activeNotices = Notice::query()
                ->where('school_id', $schoolId)
                ->active()
                ->count();

            $examQuery = Exam::query()
                ->whereHas('semester', function ($query) use ($schoolId, $user) {
                    $query->where('school_id', $schoolId);

                    if ($user->school?->semester_id) {
                        $query->where('id', $user->school->semester_id);
                    }
                });

            $ongoingExams = (clone $examQuery)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('stop_date', '>=', $today)
                ->count();

            $upcomingExams = (clone $examQuery)
                ->whereDate('start_date', '>', $today)
                ->count();

            $publishedExams = (clone $examQuery)
                ->where('publish_result', true)
                ->count();

            if ($user->school?->academic_year_id && $user->school?->semester_id) {
                $familyResultIsPublished = ! ($this->isStudent || $this->isParent)
                    || ResultPublicationStatus::termIsPublished(
                        (int) $schoolId,
                        (int) $user->school->academic_year_id,
                        (int) $user->school->semester_id
                    );

                $termResults = ! $familyResultIsPublished || $activeStudentRecordIds->isEmpty()
                    ? 0
                    : Result::query()
                        ->where('academic_year_id', $user->school->academic_year_id)
                        ->where('semester_id', $user->school->semester_id)
                        ->whereIn('student_record_id', $activeStudentRecordIds)
                        ->count();
            }
        }

        $this->snapshot = [
            'active_notices' => $activeNotices,
            'ongoing_exams' => $ongoingExams,
            'upcoming_exams' => $upcomingExams,
            'published_exams' => $publishedExams,
            'term_results' => $termResults,
            'active_students' => $activeStudentRecordIds->count(),
            'attendance_rate' => $this->attendanceRateForToday($schoolId, $today),
            'pending_admissions' => $this->pendingAdmissionsCount($user),
            'overdue_invoices' => $this->overdueInvoicesCount($user),
            'result_completion' => $this->resultCompletionRate($user, $activeStudentRecordIds),
        ];
    }

    private function loadQuickActions(User $user): void
    {
        $adminAndStaffRoles = ['super-admin', 'super_admin', 'principal', 'admin', 'teacher'];
        $adminRoles = ['super-admin', 'super_admin', 'principal', 'admin'];
        $superAdminRoles = ['super-admin', 'super_admin'];
        $viewResultsRoute = $this->currentUserCanAccessClassOnlyResultTools()
            ? 'result.view.class'
            : ($this->currentUserCanAccessSubjectResultTools() ? 'result.view.subject' : 'result');
        $viewResultsDescription = $this->currentUserCanAccessClassOnlyResultTools()
            ? 'Review class-level published result records.'
            : 'Review only the subjects and classes assigned to you.';
        $attendanceDescription = $this->isTeacher
            ? 'Record attendance for the classes assigned to you.'
            : 'Review and manage daily student attendance.';
        $syllabiDescription = $this->isTeacher
            ? 'Manage curriculum plans for your assigned classes and subjects.'
            : 'Review and manage the school curriculum plans.';
        $cbtDescription = $this->isTeacher
            ? 'Create CBT papers for your assigned subjects and classes.'
            : 'Create and manage computer-based assessments.';

        $actions = [
            [
                'title' => 'Schools',
                'description' => 'Switch schools and manage school settings.',
                'icon' => 'fas fa-school',
                'route' => 'schools.index',
                'group' => 'Operations',
                'roles' => $superAdminRoles,
                'permissions' => ['read school', 'create school', 'manage school settings'],
            ],
            [
                'title' => 'Students',
                'description' => 'Manage students, promotions, and graduations.',
                'icon' => 'fas fa-user-graduate',
                'route' => 'students.index',
                'group' => 'People',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read student', 'create student', 'promote student'],
            ],
            [
                'title' => 'Admissions',
                'description' => 'Review applications and complete enrollment decisions.',
                'icon' => 'fas fa-user-plus',
                'route' => 'admissions.registrations.index',
                'group' => 'People',
                'roles' => $adminRoles,
                'permissions' => ['read admission registration'],
            ],
            [
                'title' => 'Parents',
                'description' => 'Manage parent accounts and link children.',
                'icon' => 'fas fa-people-roof',
                'route' => 'parents.index',
                'group' => 'People',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read parent', 'create parent'],
            ],
            [
                'title' => 'Teachers',
                'description' => 'Manage teacher profiles and assignments.',
                'icon' => 'fas fa-chalkboard-teacher',
                'route' => 'teachers.index',
                'group' => 'People',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read teacher', 'create teacher'],
            ],
            [
                'title' => 'Staff Directory',
                'description' => 'Organise staff departments, biographies, and team profiles.',
                'icon' => 'fas fa-users-gear',
                'route' => 'staff.index',
                'group' => 'People',
                'roles' => $adminRoles,
                'permissions' => ['manage staff directory'],
            ],
            [
                'title' => 'Classes',
                'description' => 'Configure classes, groups, sections, and enrollment structure.',
                'icon' => 'fas fa-school-flag',
                'route' => 'classes.index',
                'group' => 'Academic',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read class'],
            ],
            [
                'title' => 'Subjects',
                'description' => 'Review curriculum subjects and teacher assignment.',
                'icon' => 'fas fa-book-open',
                'route' => 'subjects.index',
                'group' => 'Academic',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read subject', 'create subject', 'update subject'],
            ],
            [
                'title' => 'Responsibilities',
                'description' => 'View the information and tools relevant to your role.',
                'icon' => 'fas fa-briefcase',
                'route' => 'dashboard.responsibilities',
                'group' => 'Academic',
                'permissions' => ['view dashboard'],
            ],
            [
                'title' => 'Assignments',
                'description' => 'Publish class work, submit answers, and review performance.',
                'icon' => 'fas fa-clipboard-list',
                'route' => 'assignments.index',
                'group' => 'Academic',
                'permissions' => ['view assignment'],
            ],
            [
                'title' => 'Academic Calendar',
                'description' => 'Manage academic years and the active school term.',
                'icon' => 'fas fa-calendar-days',
                'route' => 'academic-years.index',
                'group' => 'Academic',
                'roles' => $adminRoles,
                'permissions' => ['read academic year'],
            ],
            [
                'title' => 'Exams',
                'description' => 'Configure exams and exam records.',
                'icon' => 'fas fa-file-signature',
                'route' => 'exams.index',
                'group' => 'Assessment',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read exam', 'read exam record'],
            ],
            [
                'title' => 'Results',
                'description' => 'Upload and manage student results.',
                'icon' => 'fas fa-chart-line',
                'route' => 'result',
                'group' => 'Assessment',
                'roles' => $adminAndStaffRoles,
                // Route "result" is protected by upload-result permission
                'permissions' => ['upload result'],
            ],
            [
                'title' => 'Review Results',
                'description' => $viewResultsDescription,
                'icon' => 'fas fa-eye',
                'route' => $viewResultsRoute,
                'group' => 'Assessment',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['view result'],
            ],
            [
                'title' => 'My Results',
                'description' => 'Open your current result sheet for the selected period.',
                'icon' => 'fas fa-file-lines',
                'route' => 'result.view.student',
                'group' => 'Academic',
                'roles' => ['student'],
                'permissions' => ['view result'],
            ],
            [
                'title' => 'Academic History',
                'description' => 'Browse your results across previous terms and years.',
                'icon' => 'fas fa-clock-rotate-left',
                'route' => 'result.history',
                'group' => 'Academic',
                'roles' => ['student'],
                'permissions' => ['view result'],
            ],
            [
                'title' => 'CBT Exams',
                'description' => 'Start an authorised CBT for your class and subject.',
                'icon' => 'fas fa-laptop-code',
                'route' => 'cbt.exams',
                'group' => 'Assessment',
                'roles' => ['student'],
                'permissions' => ['take cbt exam'],
            ],
            [
                'title' => 'CBT Results',
                'description' => 'Open your CBT result history.',
                'icon' => 'fas fa-list-alt',
                'route' => 'cbt.viewer',
                'group' => 'Assessment',
                'roles' => ['student'],
                'permissions' => ['view cbt result'],
            ],
            [
                'title' => 'Manage CBT',
                'description' => $cbtDescription,
                'icon' => 'fas fa-cogs',
                'route' => 'cbt.manage',
                'group' => 'Assessment',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['manage cbt'],
            ],
            [
                'title' => 'Children Results',
                'description' => 'Open result sheets for children linked to your account.',
                'icon' => 'fas fa-children',
                'route' => 'result.view.student',
                'group' => 'Academic',
                'roles' => ['parent'],
                'permissions' => ['view result'],
            ],
            [
                'title' => 'Children History',
                'description' => 'Review previous result history for your children.',
                'icon' => 'fas fa-timeline',
                'route' => 'result.history',
                'group' => 'Academic',
                'roles' => ['parent'],
                'permissions' => ['view result'],
            ],
            [
                'title' => 'Student Welfare',
                'description' => 'Check attendance and discipline information for your child.',
                'icon' => 'fas fa-heart',
                'route' => 'parent.student-welfare',
                'group' => 'Academic',
                'roles' => ['parent'],
                'permissions' => ['read own child attendance', 'read own child discipline'],
            ],
            [
                'title' => 'Attendance',
                'description' => $attendanceDescription,
                'icon' => 'fas fa-user-check',
                'route' => 'attendance.index',
                'group' => 'Academic',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read attendance'],
            ],
            [
                'title' => 'Discipline & Welfare',
                'description' => 'Record incidents, actions, and student welfare follow-up.',
                'icon' => 'fas fa-shield-heart',
                'route' => 'discipline.index',
                'group' => 'Student Support',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read discipline incident'],
            ],
            [
                'title' => 'Syllabi',
                'description' => $syllabiDescription,
                'icon' => 'fas fa-list-check',
                'route' => 'syllabi.index',
                'group' => 'Academic',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read syllabus', 'create syllabus'],
            ],
            [
                'title' => 'Notices',
                'description' => 'Create and publish school-wide announcements.',
                'icon' => 'fas fa-bullhorn',
                'route' => 'notices.index',
                'group' => 'Operations',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read notice', 'create notice', 'update notice'],
            ],
            [
                'title' => 'Messages',
                'description' => 'Send targeted portal and email messages to the school community.',
                'icon' => 'fas fa-paper-plane',
                'route' => 'broadcasts.manage',
                'group' => 'Communication',
                'roles' => $adminRoles,
                'permissions' => ['read broadcast message', 'create broadcast message'],
            ],
            [
                'title' => 'My Messages',
                'description' => 'Read announcements and messages sent to your account.',
                'icon' => 'fas fa-inbox',
                'route' => 'broadcasts.inbox',
                'group' => 'Communication',
                'permissions' => ['view own broadcasts'],
            ],
            [
                'title' => 'Contact Inbox',
                'description' => 'Review and respond to enquiries from the school website.',
                'icon' => 'fas fa-envelope-open-text',
                'route' => 'contacts.messages.index',
                'group' => 'Communication',
                'roles' => $adminRoles,
                'permissions' => ['read contact message'],
            ],
            [
                'title' => 'Fees',
                'description' => 'Manage fee categories and student invoices.',
                'icon' => 'fas fa-dollar-sign',
                'route' => 'fee-invoices.index',
                'group' => 'Operations',
                'roles' => $adminRoles,
                'permissions' => ['read fee', 'read fee invoice', 'read fee category'],
            ],
            [
                'title' => 'Timetables',
                'description' => 'Maintain class timetables and custom slots.',
                'icon' => 'fas fa-clock',
                'route' => 'timetables.index',
                'group' => 'Operations',
                'roles' => $adminAndStaffRoles,
                'permissions' => ['read timetable', 'create timetable'],
            ],
            [
                'title' => 'Analytics',
                'description' => 'Track admissions, engagement, and finance performance.',
                'icon' => 'fas fa-chart-pie',
                'route' => 'analytics.index',
                'group' => 'Operations',
                'roles' => $adminRoles,
                'permissions' => ['read analytics dashboard'],
            ],
            [
                'title' => 'Executive Report',
                'description' => 'Review the leadership snapshot for school operations.',
                'icon' => 'fas fa-file-contract',
                'route' => 'reports.executive',
                'group' => 'Operations',
                'roles' => $adminRoles,
                'permissions' => ['read analytics dashboard'],
            ],
            [
                'title' => 'Enrollment Analytics',
                'description' => 'Track enrollment, intake, parent links, and admission flow.',
                'icon' => 'fas fa-user-graduate',
                'route' => 'reports.enrollment-analytics',
                'group' => 'Operations',
                'roles' => $adminRoles,
                'permissions' => ['read analytics dashboard'],
            ],
            [
                'title' => 'Performance Trends',
                'description' => 'Track term performance movement across classes and subjects.',
                'icon' => 'fas fa-chart-area',
                'route' => 'reports.performance-trends',
                'group' => 'Operations',
                'roles' => $adminRoles,
                'permissions' => ['read analytics dashboard'],
            ],
            [
                'title' => 'Website Gallery',
                'description' => 'Manage photos and media shown on the public website.',
                'icon' => 'fas fa-images',
                'route' => 'gallery.manage',
                'group' => 'Website',
                'roles' => $adminRoles,
                'permissions' => ['manage gallery'],
            ],
            [
                'title' => 'Website Settings',
                'description' => 'Update school identity, contact details, and public content.',
                'icon' => 'fas fa-sliders',
                'route' => 'schools.settings',
                'group' => 'Website',
                'roles' => $adminRoles,
                'permissions' => ['manage school settings'],
            ],
            [
                'title' => 'Profile',
                'description' => 'Update personal account information.',
                'icon' => 'fas fa-user',
                'route' => 'profile.edit',
                'group' => 'Account',
            ],
            [
                'title' => 'Change Password',
                'description' => 'Secure your account credentials.',
                'icon' => 'fas fa-key',
                'route' => 'password.change',
                'group' => 'Account',
            ],
        ];

        $availableActions = array_values(array_filter(
            $actions,
            fn (array $action): bool => $this->canAccessAction($user, $action)
        ));

        $this->availableActionCount = count($availableActions);
        $groupOrder = ['People', 'Academic', 'Assessment', 'Student Support', 'Communication', 'Operations', 'Website', 'Account'];
        $this->featureGroups = collect($availableActions)
            ->groupBy('group')
            ->sortBy(function ($actions, $group) use ($groupOrder): int {
                $position = array_search($group, $groupOrder, true);

                return $position === false ? 999 : $position;
            })
            ->map(fn ($actions) => $actions->values()->all())
            ->all();
        $priorityRoutes = match (true) {
            $this->isSuperAdmin => [
                'admissions.registrations.index', 'result', 'attendance.index',
                'fee-invoices.index', 'students.index', 'contacts.messages.index',
            ],
            $this->isTeacher => [
                'dashboard.responsibilities', 'attendance.index', 'result',
                'result.view.class', 'result.view.subject', 'cbt.manage', 'syllabi.index',
            ],
            $this->isParent => [
                'result.view.student', 'parent.student-welfare', 'result.history',
                'broadcasts.inbox', 'profile.edit',
            ],
            $this->isStudent => [
                'cbt.exams', 'result.view.student', 'result.history',
                'cbt.viewer', 'broadcasts.inbox', 'profile.edit',
            ],
            default => [
                'dashboard.responsibilities', 'students.index', 'attendance.index',
                'result', 'fee-invoices.index', 'notices.index',
            ],
        };

        $this->allQuickActions = collect($availableActions)
            ->sortBy(function (array $action) use ($priorityRoutes): int {
                $position = array_search($action['route'], $priorityRoutes, true);

                return $position === false ? 999 : $position;
            })
            ->values()
            ->all();

        $this->refreshQuickActionsPage();
    }

    public function setQuickActionPage(int $page): void
    {
        $this->quickActionPage = max(1, min($page, $this->quickActionPageCount));
        $this->refreshQuickActionsPage();
    }

    public function previousQuickActionPage(): void
    {
        $this->setQuickActionPage($this->quickActionPage - 1);
    }

    public function nextQuickActionPage(): void
    {
        $this->setQuickActionPage($this->quickActionPage + 1);
    }

    private function refreshQuickActionsPage(): void
    {
        $this->quickActionPageCount = max(1, (int) ceil($this->availableActionCount / $this->quickActionsPerPage));
        $this->quickActionPage = max(1, min($this->quickActionPage, $this->quickActionPageCount));
        $offset = ($this->quickActionPage - 1) * $this->quickActionsPerPage;

        $this->quickActions = array_slice($this->allQuickActions, $offset, $this->quickActionsPerPage);
    }

    private function loadAttentionItems(User $user): void
    {
        if (! $this->isStaff || ! $user->school_id) {
            $this->attentionItems = [];

            return;
        }

        $schoolId = (int) $user->school_id;
        $items = [];

        $candidates = [
            [
                'count' => $this->pendingAdmissionsCount($user),
                'title' => 'Admission applications waiting',
                'description' => 'Review new applications and record the next decision.',
                'route' => 'admissions.registrations.index',
                'icon' => 'fas fa-user-plus',
                'tone' => 'amber',
                'permissions' => ['read admission registration'],
            ],
            [
                'count' => ContactMessage::query()->where('school_id', $schoolId)->where('status', 'new')->count(),
                'title' => 'Website enquiries unread',
                'description' => 'Open new parent and prospective-family messages.',
                'route' => 'contacts.messages.index',
                'icon' => 'fas fa-envelope',
                'tone' => 'sky',
                'permissions' => ['read contact message'],
            ],
            [
                'count' => $this->overdueInvoicesCount($user),
                'title' => 'Fee invoices overdue',
                'description' => 'Follow up invoices that still have an outstanding balance.',
                'route' => 'fee-invoices.index',
                'icon' => 'fas fa-receipt',
                'tone' => 'rose',
                'permissions' => ['read fee invoice'],
            ],
            [
                'count' => $this->unapprovedResultCount($user),
                'title' => 'Result entries awaiting approval',
                'description' => 'Review current-term scores before publication.',
                'route' => 'result.view.class',
                'icon' => 'fas fa-clipboard-check',
                'tone' => 'violet',
                'permissions' => ['view result'],
            ],
            [
                'count' => $this->absentStudentsToday($schoolId),
                'title' => 'Students absent today',
                'description' => 'Review today’s attendance records and follow up where needed.',
                'route' => 'attendance.index',
                'icon' => 'fas fa-user-clock',
                'tone' => 'orange',
                'permissions' => ['read attendance'],
            ],
        ];

        foreach ($candidates as $candidate) {
            if ($candidate['count'] > 0 && $this->canAccessAction($user, $candidate)) {
                $items[] = $candidate;
            }
        }

        $this->attentionItems = array_slice($items, 0, 5);
    }

    private function pendingAdmissionsCount(User $user): int
    {
        if (! $user->school_id || ! $user->can('read admission registration')) {
            return 0;
        }

        return AdmissionRegistration::query()
            ->where('school_id', $user->school_id)
            ->where('status', 'pending')
            ->count();
    }

    private function overdueInvoicesCount(User $user): int
    {
        if (! $user->school_id || ! $user->can('read fee invoice')) {
            return 0;
        }

        return FeeInvoice::query()
            ->whereHas('user', fn ($query) => $query->where('school_id', $user->school_id))
            ->whereDate('due_date', '<', today())
            ->whereHas('feeInvoiceRecords', fn ($query) => $query->isDue())
            ->count();
    }

    private function attendanceRateForToday(?int $schoolId, Carbon $today): ?int
    {
        if (! $schoolId || ! auth()->user()?->can('read attendance')) {
            return null;
        }

        $records = AttendanceRecord::query()
            ->whereHas('attendanceSession', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->whereDate('attendance_date', $today))
            ->get(['status']);

        if ($records->isEmpty()) {
            return null;
        }

        $present = $records->whereIn('status', ['present', 'late'])->count();

        return (int) round(($present / $records->count()) * 100);
    }

    private function absentStudentsToday(int $schoolId): int
    {
        if (! auth()->user()?->can('read attendance')) {
            return 0;
        }

        return AttendanceRecord::query()
            ->where('status', 'absent')
            ->whereHas('attendanceSession', fn ($query) => $query
                ->where('school_id', $schoolId)
                ->whereDate('attendance_date', today()))
            ->count();
    }

    private function unapprovedResultCount(User $user): int
    {
        if (! $user->school?->academic_year_id || ! $user->school?->semester_id || ! $user->can('view result')) {
            return 0;
        }

        $studentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $user->school_id,
            $user->school->academic_year_id
        );

        return Result::query()
            ->where('academic_year_id', $user->school->academic_year_id)
            ->where('semester_id', $user->school->semester_id)
            ->whereIn('student_record_id', $studentRecordIds)
            ->where('approved', false)
            ->count();
    }

    private function resultCompletionRate(User $user, $studentRecordIds): ?int
    {
        if (! $this->isStaff || ! $user->school?->academic_year_id || ! $user->school?->semester_id) {
            return null;
        }

        $results = Result::query()
            ->where('academic_year_id', $user->school->academic_year_id)
            ->where('semester_id', $user->school->semester_id)
            ->whereIn('student_record_id', $studentRecordIds)
            ->get(['approved']);

        if ($results->isEmpty()) {
            return null;
        }

        return (int) round(($results->where('approved', true)->count() / $results->count()) * 100);
    }

    private function canAccessAction(User $user, array $action): bool
    {
        if (empty($action['route']) || ! Route::has($action['route'])) {
            return false;
        }

        if ($this->isRestrictedTeacherPortalUser($user) && ! $this->restrictedTeacherCanAccessRoute($action['route'], $user)) {
            return false;
        }

        if (! empty($action['roles']) && is_array($action['roles']) && ! $user->hasAnyRole($action['roles'])) {
            return false;
        }

        if (! empty($action['permissions']) && is_array($action['permissions']) && ! $this->hasAnyPermission($user, $action['permissions'])) {
            return false;
        }

        return true;
    }

    private function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (is_string($permission) && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function loadStats(User $user): void
    {
        $schoolId = $user->school_id;
        $activeStudentsCount = $this->getActiveStudentsCount($schoolId);

        $this->stats = [
            'schools' => $this->isSuperAdmin ? School::count() : 0,
            'class_groups' => $schoolId ? ClassGroup::query()->count() : 0,
            'classes' => $schoolId ? MyClass::whereHas('classGroup', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->count() : 0,
            'sections' => $schoolId ? Section::whereHas('myClass.classGroup', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->count() : 0,
            'subjects' => $schoolId ? Subject::whereHas('myClass.classGroup', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->count() : 0,
            'active_students' => $activeStudentsCount,
            'inactive_students' => $this->getInactiveStudentsCount($schoolId, $activeStudentsCount),
            'graduated_students' => $this->getGraduatedStudentsCount($schoolId),
            'teachers' => User::where('school_id', $schoolId)->role('teacher')->count(),
            'parents' => User::where('school_id', $schoolId)->role('parent')->count(),
            'total_notices' => $schoolId ? Notice::query()->count() : 0,
            'total_exams' => $schoolId ? Exam::whereHas('semester', function ($q) use ($schoolId, $user) {
                $q->where('school_id', $schoolId);

                if ($user->school?->semester_id) {
                    $q->where('id', $user->school->semester_id);
                }
            })->count() : 0,
        ];
    }

    private function getActiveStudentsCount(?int $schoolId): int
    {
        if (! $schoolId) {
            return 0;
        }

        return StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $schoolId,
            auth()->user()?->school?->academic_year_id
        )->count();
    }

    private function getInactiveStudentsCount(?int $schoolId, ?int $activeStudentsCount = null): int
    {
        if (! $schoolId) {
            return 0;
        }

        return User::query()
            ->where('school_id', $schoolId)
            ->role('student')
            ->where('locked', true)
            ->whereHas('studentRecord', fn ($query) => $query->where('is_graduated', false))
            ->count();
    }

    private function getGraduatedStudentsCount(?int $schoolId): int
    {
        if (! $schoolId) {
            return 0;
        }

        return User::where('school_id', $schoolId)
            ->role('student')
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('student_records')
                    ->where('is_graduated', true)
                    ->whereNotNull('user_id');
            })
            ->count();
    }

    private function loadStudentPanel(User $user): void
    {
        $studentRecord = $user->studentRecord;
        if (! $studentRecord) {
            $this->studentPanel = [];

            return;
        }

        $currentAcademicYearId = $user->school?->academic_year_id;
        $currentSemesterId = $user->school?->semester_id;

        $resultQuery = Result::query()->where('student_record_id', $studentRecord->id);

        if ($currentAcademicYearId) {
            $resultQuery->where('academic_year_id', $currentAcademicYearId);
        }

        if ($currentSemesterId) {
            $resultQuery->where('semester_id', $currentSemesterId);
        }

        $resultPublished = (bool) ($user->school_id && $currentAcademicYearId && $currentSemesterId)
            && ResultPublicationStatus::termIsPublished(
                (int) $user->school_id,
                (int) $currentAcademicYearId,
                (int) $currentSemesterId
            );

        if (! $resultPublished) {
            $resultQuery->whereRaw('1 = 0');
        }

        $className = $studentRecord->academicYearClass?->name ?? $studentRecord->myClass?->name ?? 'Not assigned';
        $sectionName = $studentRecord->academicYearSection?->name ?? $studentRecord->section?->name ?? 'Not assigned';

        $this->studentPanel = [
            'admission_number' => $studentRecord->admission_number ?: 'N/A',
            'class_name' => $className,
            'section_name' => $sectionName,
            'subject_count' => $studentRecord->studentSubjects()->count(),
            'result_count' => (clone $resultQuery)->count(),
            'approved_result_count' => (clone $resultQuery)->where('approved', true)->count(),
            'average_score' => number_format((float) ((clone $resultQuery)->avg('total_score') ?? 0), 1),
            'result_published' => $resultPublished,
        ];
    }

    private function loadParentPanel(User $user): void
    {
        $children = $user->children()
            ->where('users.school_id', $user->school_id)
            ->whereNull('users.deleted_at')
            ->with('studentRecord')
            ->get();
        if ($children->isEmpty()) {
            $this->parentPanel = [
                'total_children' => 0,
                'hidden_count' => 0,
                'children' => [],
            ];

            return;
        }

        $classIds = $children
            ->pluck('studentRecord.my_class_id')
            ->filter()
            ->unique()
            ->values();

        $sectionIds = $children
            ->pluck('studentRecord.section_id')
            ->filter()
            ->unique()
            ->values();

        $classNames = $classIds->isEmpty()
            ? collect()
            : MyClass::whereIn('id', $classIds)->pluck('name', 'id');

        $sectionNames = $sectionIds->isEmpty()
            ? collect()
            : Section::whereIn('id', $sectionIds)->pluck('name', 'id');

        $limit = 6;
        $displayChildren = $children
            ->take($limit)
            ->map(function (User $child) use ($classNames, $sectionNames): array {
                $record = $child->studentRecord;

                $className = $record?->my_class_id ? ($classNames[$record->my_class_id] ?? 'Not assigned') : 'Not assigned';
                $sectionName = $record?->section_id ? ($sectionNames[$record->section_id] ?? 'Not assigned') : 'Not assigned';

                return [
                    'name' => $child->name,
                    'class_name' => $className,
                    'section_name' => $sectionName,
                    'admission_number' => $record?->admission_number ?: 'N/A',
                ];
            })
            ->values()
            ->all();

        $this->parentPanel = [
            'total_children' => $children->count(),
            'hidden_count' => max($children->count() - $limit, 0),
            'children' => $displayChildren,
        ];
    }

    private function loadTeacherPanel(User $user): void
    {
        $teacherPanel = app(TeacherResponsibilityBuilder::class)->build($user);

        $teacherToolCount = collect($this->quickActions)
            ->reject(fn (array $action) => in_array($action['route'] ?? '', ['profile.edit', 'password.change'], true))
            ->count();

        $teacherPanel['teacher_tools'] = $teacherToolCount;
        $teacherPanel['focus_items'] = $this->loadTeacherFocusItems(
            $user,
            (int) ($teacherPanel['class_teacher_classes'] ?? 0),
            (int) ($teacherPanel['teaching_assignments'] ?? 0)
        );

        $this->teacherPanel = $teacherPanel;
    }

    private function loadTeacherFocusItems(User $user, int $managedClassCount, int $teachingAssignmentCount): array
    {
        $items = [
            [
                'title' => 'Responsibilities',
                'description' => 'Open the page that shows the information relevant to your role.',
                'icon' => 'fas fa-briefcase',
                'route' => 'dashboard.responsibilities',
                'permissions' => ['view dashboard'],
                'tone' => 'bg-violet-600 text-white',
                'cta' => 'Open page',
            ],
            [
                'title' => 'Attendance',
                'description' => 'Record attendance for the classes assigned to you.',
                'icon' => 'fas fa-user-check',
                'route' => 'attendance.index',
                'roles' => ['teacher'],
                'permissions' => ['read attendance'],
                'tone' => 'bg-blue-600 text-white',
                'cta' => 'Open page',
                'requires_managed_classes' => true,
            ],
            [
                'title' => 'Results',
                'description' => 'Upload or review results for your assigned subjects.',
                'icon' => 'fas fa-chart-line',
                'route' => 'result',
                'roles' => ['teacher'],
                'permissions' => ['upload result'],
                'tone' => 'bg-emerald-600 text-white',
                'cta' => 'Open page',
                'requires_teaching_assignments' => true,
            ],
            [
                'title' => 'CBT Management',
                'description' => 'Manage CBT assessments and questions for your assigned subjects.',
                'icon' => 'fas fa-laptop-code',
                'route' => 'cbt.manage',
                'roles' => ['teacher'],
                'permissions' => ['manage cbt'],
                'tone' => 'bg-amber-500 text-slate-950',
                'cta' => 'Open page',
                'requires_teaching_assignments' => true,
            ],
            [
                'title' => 'Syllabi',
                'description' => 'Manage syllabi for the classes and subjects assigned to you.',
                'icon' => 'fas fa-list-check',
                'route' => 'syllabi.index',
                'roles' => ['teacher'],
                'permissions' => ['read syllabus', 'create syllabus'],
                'tone' => 'bg-rose-600 text-white',
                'cta' => 'Open page',
                'requires_teaching_assignments' => true,
            ],
            [
                'title' => 'Timetable',
                'description' => 'View the timetable for your classes and teaching schedule.',
                'icon' => 'fas fa-clock',
                'route' => 'timetables.index',
                'roles' => ['teacher'],
                'permissions' => ['read timetable', 'create timetable'],
                'tone' => 'bg-slate-800 text-white',
                'cta' => 'Open page',
            ],
        ];

        return array_values(array_filter($items, function (array $item) use ($managedClassCount, $teachingAssignmentCount, $user): bool {
            if (($item['requires_managed_classes'] ?? false) && $managedClassCount === 0) {
                return false;
            }

            if (($item['requires_teaching_assignments'] ?? false) && $teachingAssignmentCount === 0) {
                return false;
            }

            return $this->canAccessAction($user, $item);
        }));
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-stats');
    }
}
