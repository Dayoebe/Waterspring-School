<?php

namespace App\Livewire\Layouts;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use App\Traits\RestrictsTeacherPortalAccess;
use App\Traits\RestrictsTeacherResultViewing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component;

class Menu extends Component
{
    use RestrictsTeacherPortalAccess;
    use RestrictsTeacherResultViewing;

    public array $menu = [];

    public function mount(): void
    {
        $this->menu = array_merge(
            $this->overviewMenu(),
            $this->peopleMenu(),
            $this->academicsMenu(),
            $this->operationsMenu(),
            $this->communicationMenu(),
            $this->administrationMenu(),
        );
    }

    protected function overviewMenu(): array
    {
        return [
            ['header' => 'Workspace'],
            [
                'type' => 'menu-item',
                'icon' => 'fas fa-tachometer-alt',
                'text' => 'Dashboard',
                'route' => 'dashboard',
                'permissions' => ['view dashboard'],
            ],
            [
                'type' => 'menu-item',
                'icon' => 'fas fa-briefcase',
                'text' => 'My Responsibilities',
                'route' => 'dashboard.responsibilities',
                'permissions' => ['view dashboard'],
            ],
            [
                'text' => 'My Learning',
                'icon' => 'fas fa-graduation-cap',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'View Student Results',
                        'route' => 'result.view.student',
                        'roles' => ['student', 'parent'],
                        'permissions' => ['view result'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Academic History',
                        'route' => 'result.history',
                        'roles' => ['student', 'parent'],
                        'permissions' => ['view result'],
                    ],
                ])),
            ],
        ];
    }

    protected function peopleMenu(): array
    {
        return [
            ['header' => 'People'],
            [
                'text' => 'Students & Parents',
                'icon' => 'fas fa-user-graduate',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'All Students',
                        'route' => 'students.index',
                        'permissions' => ['read student'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Admission Applications',
                        'route' => 'admissions.registrations.index',
                        'permissions' => ['read admission registration'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Student',
                        'route' => 'students.create',
                        'params' => ['mode' => 'create'],
                        'permissions' => ['create student'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Promote Students',
                        'route' => 'students.promote',
                        'permissions' => ['promote student', 'read promotion'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Graduate Students',
                        'route' => 'students.graduate',
                        'permissions' => ['graduate student'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Graduation History',
                        'route' => 'students.graduations',
                        'permissions' => ['view graduations'],
                        'section' => 'Students & Admissions',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Parents',
                        'route' => 'parents.index',
                        'permissions' => ['read parent'],
                        'section' => 'Parents',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Parent',
                        'route' => 'parents.index',
                        'params' => ['mode' => 'create'],
                        'permissions' => ['create parent'],
                        'section' => 'Parents',
                    ],
                ])),
            ],
            [
                'text' => 'Staff & Access',
                'icon' => 'fas fa-users-gear',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Staff Directory',
                        'route' => 'staff.index',
                        'permissions' => ['manage staff directory'],
                        'section' => 'Staff Directory',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Teachers',
                        'route' => 'teachers.index',
                        'permissions' => ['read teacher'],
                        'section' => 'Teachers',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Teacher',
                        'route' => 'teachers.create',
                        'params' => ['mode' => 'create'],
                        'permissions' => ['create teacher'],
                        'section' => 'Teachers',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Administrators',
                        'route' => 'admins.index',
                        'permissions' => ['read admin'],
                        'section' => 'Administrators & Access',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Administrator',
                        'route' => 'admins.index',
                        'params' => ['mode' => 'create'],
                        'permissions' => ['create admin'],
                        'section' => 'Administrators & Access',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Users & Roles',
                        'icon' => 'fas fa-users-gear',
                        'route' => 'users.roles',
                        'permissions' => ['manage user roles'],
                        'section' => 'Administrators & Access',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Pending Applications',
                        'route' => 'account-applications.index',
                        'permissions' => ['read applicant'],
                        'can' => ['viewAny', [User::class, 'applicant']],
                        'section' => 'Account Applications',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Rejected Applications',
                        'route' => 'account-applications.rejected-applications',
                        'permissions' => ['read applicant'],
                        'can' => ['viewAny', [User::class, 'applicant']],
                        'section' => 'Account Applications',
                    ],
                ])),
            ],
        ];
    }

    protected function academicsMenu(): array
    {
        $canAccessClassOnlyResultTools = $this->currentUserCanAccessClassOnlyResultTools();
        $canAccessSubjectResultTools = $this->currentUserCanAccessSubjectResultTools();

        return [
            ['header' => 'Academics'],
            [
                'type' => 'menu-item',
                'text' => 'Assignments',
                'icon' => 'fas fa-clipboard-list',
                'route' => 'assignments.index',
                'permissions' => ['view assignment'],
            ],
            [
                'text' => 'Classes & Subjects',
                'icon' => 'fas fa-book-open',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'All Classes',
                        'route' => 'classes.index',
                        'permissions' => ['read class'],
                        'section' => 'Classes',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Class Groups',
                        'route' => 'class-groups.index',
                        'permissions' => ['read class group'],
                        'section' => 'Classes',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Sections',
                        'route' => 'sections.index',
                        'permissions' => ['read section', 'create section'],
                        'section' => 'Classes',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'All Subjects',
                        'route' => 'subjects.index',
                        'permissions' => ['read subject'],
                        'section' => 'Subjects & Syllabi',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Subject',
                        'route' => 'subjects.create',
                        'permissions' => ['create subject'],
                        'section' => 'Subjects & Syllabi',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Assign Subject Teachers',
                        'route' => 'subjects.assign-teacher',
                        'permissions' => ['update subject'],
                        'section' => 'Subjects & Syllabi',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Syllabi & Curriculum',
                        'icon' => 'fas fa-list-check',
                        'route' => 'syllabi.index',
                        'permissions' => ['read syllabus', 'create syllabus'],
                        'section' => 'Subjects & Syllabi',
                    ],
                ])),
            ],
            [
                'text' => 'Calendar & Timetables',
                'icon' => 'fas fa-calendar-alt',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'School Calendar',
                        'route' => 'calendar.index',
                        'permissions' => ['read school calendar'],
                        'section' => 'Academic Calendar',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Academic Years',
                        'route' => 'academic-years.index',
                        'permissions' => ['read academic year'],
                        'can' => ['viewAny', AcademicYear::class],
                        'section' => 'Academic Calendar',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Terms',
                        'route' => 'semesters.index',
                        'permissions' => ['read semester'],
                        'can' => ['viewAny', Semester::class],
                        'section' => 'Academic Calendar',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'All Timetables',
                        'route' => 'timetables.index',
                        'permissions' => ['read timetable'],
                        'section' => 'Timetables',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Timetable',
                        'route' => 'timetables.create',
                        'permissions' => ['create timetable'],
                        'section' => 'Timetables',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Timetable Activities',
                        'route' => 'custom-timetable-items.index',
                        'permissions' => ['read custom timetable item'],
                        'section' => 'Timetables',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add Timetable Activity',
                        'route' => 'custom-timetable-items.create',
                        'permissions' => ['create custom timetable item'],
                        'section' => 'Timetables',
                    ],
                ])),
            ],
            [
                'text' => 'Exams & Results',
                'icon' => 'fas fa-chart-line',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Manage Exams',
                        'route' => 'exams.index',
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'permissions' => ['read exam'],
                        'section' => 'Exams',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Exam Records',
                        'route' => 'exam-records.index',
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'permissions' => ['read exam record'],
                        'section' => 'Exams',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Grading Systems',
                        'route' => 'grade-systems.index',
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'permissions' => ['read grade system', 'create grade system'],
                        'section' => 'Exams',
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Exam Papers',
                        'route' => 'exam-papers.viewer',
                        'roles' => ['student', 'parent'],
                        'permissions' => ['view exam paper'],
                        'section' => 'Exams',
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-tachometer-alt',
                        'text' => 'Results Dashboard',
                        'route' => 'result',
                        'permissions' => ['upload result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-user-edit',
                        'text' => 'Enter Student Results',
                        'route' => 'result.upload.individual',
                        'permissions' => ['upload result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-users-cog',
                        'text' => 'Upload Results in Bulk',
                        'route' => 'result.upload.bulk',
                        'permissions' => ['upload result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ],
                    $canAccessClassOnlyResultTools ? [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-users',
                        'text' => 'Class Results',
                        'route' => 'result.view.class',
                        'permissions' => ['view result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ] : null,
                    $canAccessSubjectResultTools ? [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-book',
                        'text' => 'Subject Results',
                        'route' => 'result.view.subject',
                        'permissions' => ['view result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ] : null,
                    $canAccessClassOnlyResultTools ? [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-user-graduate',
                        'text' => 'Student Results',
                        'route' => 'result.view.student',
                        'permissions' => ['view result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ] : null,
                    $canAccessClassOnlyResultTools ? [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-history',
                        'text' => 'Academic History',
                        'route' => 'result.history',
                        'permissions' => ['view result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ] : null,
                    $canAccessClassOnlyResultTools ? [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-calendar-alt',
                        'text' => 'Annual Class Results',
                        'route' => 'result.annual',
                        'permissions' => ['view result'],
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'section' => 'Results',
                    ] : null,
                    [
                        'type' => 'menu-item',
                        'text' => 'Result Checker',
                        'route' => 'exams.result-checker',
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'permissions' => ['check result'],
                        'section' => 'Results',
                    ],
                ])),
            ],
            [
                'text' => 'Computer-Based Tests',
                'icon' => 'fas fa-laptop',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Take CBT Exams',
                        'route' => 'cbt.exams',
                        'roles' => ['student'],
                        'permissions' => ['take cbt exam'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'CBT Results',
                        'route' => 'cbt.viewer',
                        'roles' => ['student'],
                        'permissions' => ['view cbt result'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Manage CBT',
                        'route' => 'cbt.manage',
                        'roles' => ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'],
                        'permissions' => ['manage cbt'],
                    ],
                ])),
            ],
        ];
    }

    protected function operationsMenu(): array
    {
        return [
            ['header' => 'School Operations'],
            [
                'text' => 'Attendance & Discipline',
                'icon' => 'fas fa-user-check',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Daily Attendance',
                        'route' => 'attendance.index',
                        'permissions' => ['read attendance'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Discipline Incidents',
                        'route' => 'discipline.index',
                        'permissions' => ['read discipline incident'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'My Child’s Attendance & Discipline',
                        'route' => 'parent.student-welfare',
                        'permissions' => ['read own child attendance', 'read own child discipline'],
                    ],
                ])),
            ],
            [
                'text' => 'Fees & Payments',
                'icon' => 'fas fa-wallet',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Fee Invoices',
                        'route' => 'fee-invoices.index',
                        'permissions' => ['read fee invoice'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Create Fee Invoice',
                        'route' => 'fee-invoices.create',
                        'permissions' => ['create fee invoice'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Fees',
                        'route' => 'fees.index',
                        'permissions' => ['read fee'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Create Fee',
                        'route' => 'fees.create',
                        'permissions' => ['create fee'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Fee Categories',
                        'route' => 'fee-categories.index',
                        'permissions' => ['read fee category'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Create Fee Category',
                        'route' => 'fee-categories.create',
                        'permissions' => ['create fee category'],
                    ],
                ])),
            ],
        ];
    }

    protected function communicationMenu(): array
    {
        return [
            ['header' => 'Communication'],
            [
                'text' => 'Messages & Notices',
                'icon' => 'fas fa-comments',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-bullhorn',
                        'text' => 'School Notices',
                        'route' => 'notices.index',
                        'permissions' => ['read notice', 'create notice', 'update notice'],
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-paper-plane',
                        'text' => 'Send Announcements',
                        'route' => 'broadcasts.manage',
                        'permissions' => ['read broadcast message', 'create broadcast message'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'My Announcements',
                        'route' => 'broadcasts.inbox',
                        'permissions' => ['view own broadcasts'],
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-envelope',
                        'text' => 'Website Enquiries',
                        'route' => 'contacts.messages.index',
                        'permissions' => ['read contact message'],
                    ],
                ])),
            ],
            [
                'text' => 'Website & Media',
                'icon' => 'fas fa-globe',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'Website Settings',
                        'icon' => 'fas fa-sliders-h',
                        'route' => 'schools.settings',
                        'permissions' => ['manage school settings'],
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-images',
                        'text' => 'School Gallery',
                        'route' => 'gallery.manage',
                        'permissions' => ['manage gallery'],
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-photo-video',
                        'text' => 'Media Library',
                        'route' => 'media-library.index',
                        'permissions' => ['manage media library'],
                    ],
                ])),
            ],
        ];
    }

    protected function administrationMenu(): array
    {
        return [
            ['header' => 'Administration'],
            [
                'text' => 'Reports & Analytics',
                'icon' => 'fas fa-chart-pie',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-chart-bar',
                        'text' => 'Analytics Overview',
                        'route' => 'analytics.index',
                        'permissions' => ['read analytics dashboard'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Executive Report',
                        'route' => 'reports.executive',
                        'permissions' => ['read analytics dashboard'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Enrollment Analytics',
                        'route' => 'reports.enrollment-analytics',
                        'permissions' => ['read analytics dashboard'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Performance Trends',
                        'route' => 'reports.performance-trends',
                        'permissions' => ['read analytics dashboard'],
                    ],
                ])),
            ],
            [
                'text' => 'School Settings',
                'icon' => 'fas fa-school',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'text' => 'All Schools',
                        'route' => 'schools.index',
                        'permissions' => ['read school'],
                    ],
                    [
                        'type' => 'menu-item',
                        'text' => 'Add School',
                        'route' => 'schools.index',
                        'params' => ['mode' => 'create'],
                        'permissions' => ['create school'],
                    ],
                ])),
            ],
            [
                'text' => 'My Account',
                'icon' => 'fas fa-user',
                'submenu' => array_values(array_filter([
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-user',
                        'text' => 'Profile',
                        'route' => 'profile.edit',
                        'permissions' => ['manage own profile'],
                    ],
                    [
                        'type' => 'menu-item',
                        'icon' => 'fas fa-key',
                        'text' => 'Change Password',
                        'route' => 'password.change',
                        'permissions' => ['change own password'],
                    ],
                ])),
            ],
        ];
    }

    public function isVisible(array $item): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (empty($item['coming_soon']) && ! empty($item['route']) && ! Route::has($item['route'])) {
            return false;
        }

        if ($this->isRestrictedTeacherPortalUser($user)) {
            if (! empty($item['coming_soon'])) {
                return false;
            }

            if (! empty($item['route']) && ! $this->restrictedTeacherCanAccessRoute($item['route'], $user)) {
                return false;
            }
        }

        if (! empty($item['permissions']) && is_array($item['permissions']) && ! $this->hasAnyPermission($item['permissions'])) {
            return false;
        }

        if (! empty($item['roles']) && is_array($item['roles']) && ! $user->hasAnyRole($item['roles'])) {
            return false;
        }

        if (array_key_exists('can', $item) && ! $this->passesCanCheck($item['can'])) {
            return false;
        }

        if (! empty($item['can_any']) && is_array($item['can_any']) && ! $this->passesAnyCanChecks($item['can_any'])) {
            return false;
        }

        if (! empty($item['submenu']) && is_array($item['submenu']) && $this->visibleSubmenu($item['submenu']) === []) {
            return false;
        }

        return true;
    }

    public function visibleSubmenu(array $submenu): array
    {
        return array_values(array_filter($submenu, fn (array $item): bool => $this->isVisible($item)));
    }

    protected function hasAnyPermission(array $permissions): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (is_string($permission) && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function passesCanCheck(mixed $can): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (is_string($can)) {
            return $user->can($can);
        }

        if (is_array($can) && array_is_list($can) && isset($can[0]) && is_string($can[0])) {
            $ability = $can[0];
            $arguments = $can[1] ?? [];

            return $user->can($ability, $arguments);
        }

        return false;
    }

    protected function passesAnyCanChecks(array $canChecks): bool
    {
        foreach ($canChecks as $canCheck) {
            if ($this->passesCanCheck($canCheck)) {
                return true;
            }
        }

        return false;
    }

    /** Build the searchable navigation from authorized destinations only. */
    public function navigationSections(): array
    {
        $sections = [];
        $header = 'Workspace';
        $seen = [];
        $visibleRoutes = collect($this->menu)
            ->flatMap(fn (array $item) => $item['submenu'] ?? [$item])
            ->filter(fn (array $item) => $this->isVisible($item))
            ->pluck('route')->filter()->all();

        foreach ($this->menu as $item) {
            if (isset($item['header'])) {
                $header = $item['header'];

                continue;
            }

            if (! $this->isVisible($item)) {
                continue;
            }

            $item['id'] = Str::slug($item['text']);
            if (isset($item['submenu'])) {
                $children = [];
                foreach ($this->visibleSubmenu($item['submenu']) as $child) {
                    $child = $this->navigationLink($child, $visibleRoutes, $item['text']);
                    if (isset($seen[$child['route_url']])) {
                        continue;
                    }
                    $seen[$child['route_url']] = true;
                    $children[] = $child;
                }
                if ($children === []) {
                    continue;
                }
                $item['submenu'] = $children;
                $item['active'] = collect($children)->contains('active', true);
                $item['route_url'] = null;
                $item['search'] = implode(' ', array_column($children, 'search'));
            } else {
                $item = $this->navigationLink($item, $visibleRoutes);
                if (isset($seen[$item['route_url']])) {
                    continue;
                }
                $seen[$item['route_url']] = true;
            }
            $sections[$header]['header'] = $header;
            $sections[$header]['items'][] = $item;
        }

        return array_values($sections);
    }

    protected function navigationLink(array $item, array $visibleRoutes, string $group = ''): array
    {
        $parameters = array_merge($item['params'] ?? [], $item['query'] ?? []);
        $item['route_url'] = route($item['route'], $parameters);
        $item['search'] = implode(' ', [$group, $item['section'] ?? '', $item['text'], str_replace(['.', '-'], ' ', $item['route'])]);
        $currentRoute = Route::currentRouteName();
        $currentMode = request()->query('mode', 'list');
        $expectedMode = $parameters['mode'] ?? 'list';
        $item['active'] = $currentRoute === $item['route']
            && ($expectedMode === $currentMode || ($expectedMode === 'list' && in_array($currentMode, ['edit', 'view'], true)));

        // Some Livewire forms can also be opened from their directory via ?mode=create.
        if ($currentMode === 'create' && $expectedMode === 'create' && str_ends_with($item['route'], '.create')) {
            $item['active'] = in_array($currentRoute, [$item['route'], substr($item['route'], 0, -7).'.index'], true);
        }

        // Keep the directory highlighted while viewing or editing one of its records.
        if (! in_array($currentRoute, $visibleRoutes, true) && str_ends_with($item['route'], '.index') && $expectedMode === 'list') {
            $prefix = substr($item['route'], 0, -6);
            $item['active'] = Str::is([$prefix.'.show', $prefix.'.edit', $prefix.'.create'], $currentRoute ?? '');
        }

        return $item;
    }

    public function render()
    {
        return view('livewire.layouts.menu', ['sections' => $this->navigationSections()]);
    }
}
