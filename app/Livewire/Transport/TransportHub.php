<?php

namespace App\Livewire\Transport;

use App\Models\AuthorizedPickupGuardian;
use App\Models\StudentTransportAssignment;
use App\Models\TransportEvent;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use App\Services\SchoolTransportService;
use App\Services\TransportNotificationService;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TransportHub extends Component
{
    public string $routeName = '';

    public string $routeCode = '';

    public string $vehicleLabel = '';

    public string $driverName = '';

    public string $driverPhone = '';

    public string $stopRouteId = '';

    public string $stopName = '';

    public int $stopSequence = 1;

    public string $morningTime = '';

    public string $afternoonTime = '';

    public string $landmark = '';

    public string $assignmentStudentId = '';

    public string $assignmentRouteId = '';

    public string $assignmentStopId = '';

    public string $assignmentDirection = 'both';

    public string $guardianStudentId = '';

    public string $guardianName = '';

    public string $guardianRelationship = '';

    public string $guardianPhone = '';

    public string $verificationGuardianId = '';

    public string $pickupCode = '';

    public string $verificationNotes = '';

    public string $movementAssignmentId = '';

    public string $movementType = 'arrived';

    public string $movementNotes = '';

    public string $childFilter = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user?->can('manage school transport') || $user?->can('view child transport'), 403);
        if ($user->hasRole('parent')) {
            $this->childFilter = (string) ($user->children()->where('users.school_id', $user->school_id)->value('users.id') ?? '');
        }
    }

    public function updatedAssignmentRouteId(): void
    {
        $this->assignmentStopId = '';
    }

    public function createRoute(SchoolTransportService $service): void
    {
        $this->assertManager();
        $schoolId = auth()->user()->school_id;
        $validated = $this->validate([
            'routeName' => ['required', 'string', 'max:255'],
            'routeCode' => ['required', 'string', 'max:40', Rule::unique('transport_routes', 'code')->where('school_id', $schoolId)],
            'vehicleLabel' => ['nullable', 'string', 'max:255'],
            'driverName' => ['nullable', 'string', 'max:255'],
            'driverPhone' => ['nullable', 'string', 'max:40'],
        ]);
        $service->createRoute(auth()->user(), [
            'name' => $validated['routeName'],
            'code' => $validated['routeCode'],
            'vehicle_label' => $validated['vehicleLabel'],
            'driver_name' => $validated['driverName'],
            'driver_phone' => $validated['driverPhone'],
        ]);
        $this->reset(['routeName', 'routeCode', 'vehicleLabel', 'driverName', 'driverPhone']);
        session()->flash('success', 'Transport route created.');
    }

    public function addStop(SchoolTransportService $service): void
    {
        $this->assertManager();
        $validated = $this->validate([
            'stopRouteId' => ['required', 'integer'],
            'stopName' => ['required', 'string', 'max:255'],
            'stopSequence' => ['required', 'integer', 'min:1', 'max:999'],
            'morningTime' => ['nullable', 'date_format:H:i'],
            'afternoonTime' => ['nullable', 'date_format:H:i'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $route = TransportRoute::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['stopRouteId']);
            $service->addStop($route, auth()->user(), [
                'name' => $validated['stopName'],
                'sequence' => $validated['stopSequence'],
                'morning_time' => $validated['morningTime'],
                'afternoon_time' => $validated['afternoonTime'],
                'landmark' => $validated['landmark'],
            ]);
            $this->reset(['stopName', 'morningTime', 'afternoonTime', 'landmark']);
            $this->stopSequence++;
            session()->flash('success', 'Route stop added.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function assignStudent(SchoolTransportService $service): void
    {
        $this->assertManager();
        $validated = $this->validate([
            'assignmentStudentId' => ['required', 'integer'],
            'assignmentRouteId' => ['required', 'integer'],
            'assignmentStopId' => ['required', 'integer'],
            'assignmentDirection' => ['required', 'in:morning,afternoon,both'],
        ]);
        try {
            $service->assignStudent(
                User::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['assignmentStudentId']),
                TransportRoute::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['assignmentRouteId']),
                TransportStop::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['assignmentStopId']),
                auth()->user(),
                $validated['assignmentDirection']
            );
            $this->reset(['assignmentStudentId', 'assignmentRouteId', 'assignmentStopId']);
            $this->assignmentDirection = 'both';
            session()->flash('success', 'Student assigned to the transport route.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function deactivateAssignment(int $assignmentId, SchoolTransportService $service): void
    {
        $this->assertManager();
        $assignment = StudentTransportAssignment::query()->where('school_id', auth()->user()->school_id)->findOrFail($assignmentId);
        $service->deactivateAssignment($assignment, auth()->user());
        session()->flash('success', 'Transport assignment deactivated.');
    }

    public function authorizeGuardian(SchoolTransportService $service): void
    {
        $this->assertManager();
        $validated = $this->validate([
            'guardianStudentId' => ['required', 'integer'],
            'guardianName' => ['required', 'string', 'max:255'],
            'guardianRelationship' => ['required', 'string', 'max:80'],
            'guardianPhone' => ['required', 'string', 'max:40'],
        ]);
        try {
            $result = $service->authorizeGuardian(
                User::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['guardianStudentId']),
                auth()->user(),
                [
                    'name' => $validated['guardianName'],
                    'relationship' => $validated['guardianRelationship'],
                    'phone' => $validated['guardianPhone'],
                ]
            );
            $this->reset(['guardianName', 'guardianRelationship', 'guardianPhone']);
            session()->flash('pickup_code', $result['pickup_code']);
            session()->flash('success', 'Guardian authorised. Share the one-time displayed code securely; it cannot be retrieved later.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function revokeGuardian(int $guardianId, SchoolTransportService $service): void
    {
        $this->assertManager();
        $guardian = AuthorizedPickupGuardian::query()->where('school_id', auth()->user()->school_id)->findOrFail($guardianId);
        $service->revokeGuardian($guardian, auth()->user());
        session()->flash('success', 'Guardian pickup authority revoked.');
    }

    public function verifyPickup(SchoolTransportService $service): void
    {
        $this->assertManager();
        $validated = $this->validate([
            'verificationGuardianId' => ['required', 'integer'],
            'pickupCode' => ['required', 'digits:6'],
            'verificationNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $guardian = AuthorizedPickupGuardian::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['verificationGuardianId']);
            $service->verifyPickup($guardian, auth()->user(), $validated['pickupCode'], $validated['verificationNotes']);
            $this->reset(['verificationGuardianId', 'pickupCode', 'verificationNotes']);
            session()->flash('success', 'Pickup guardian verified and recorded.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function recordMovement(SchoolTransportService $service, TransportNotificationService $notifications): void
    {
        $this->assertManager();
        $validated = $this->validate([
            'movementAssignmentId' => ['required', 'integer'],
            'movementType' => ['required', 'in:boarded,arrived,dropped_off'],
            'movementNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $assignment = StudentTransportAssignment::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['movementAssignmentId']);
            $event = $service->recordMovement($assignment, auth()->user(), $validated['movementType'], $validated['movementNotes']);
            if (in_array($event->event_type, ['arrived', 'dropped_off'], true)) {
                $notifications->notifyParents($event);
            }
            $this->reset(['movementAssignmentId', 'movementNotes']);
            $this->movementType = 'arrived';
            session()->flash('success', 'Transport event recorded. Parent alerts were queued when applicable.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    private function assertManager(): void
    {
        abort_unless(auth()->user()?->can('manage school transport'), 403);
    }

    public function render()
    {
        $user = auth()->user();
        $isParent = $user->hasRole('parent');
        $childIds = $isParent
            ? $user->children()->where('users.school_id', $user->school_id)->pluck('users.id')
            : collect();
        $children = $isParent
            ? User::query()->whereIn('id', $childIds)->orderBy('name')->get(['id', 'name'])
            : collect();
        $visibleStudentIds = $isParent
            ? ($this->childFilter !== '' && $childIds->contains((int) $this->childFilter) ? [(int) $this->childFilter] : $childIds->all())
            : [];
        $routes = TransportRoute::query()->where('school_id', $user->school_id)->with('stops')->orderBy('name')->get();
        $students = $isParent ? collect() : User::query()->where('school_id', $user->school_id)->role('student')->orderBy('name')->get(['id', 'name']);
        $assignmentStops = $this->assignmentRouteId !== ''
            ? TransportStop::query()->where('school_id', $user->school_id)->where('transport_route_id', $this->assignmentRouteId)->where('is_active', true)->orderBy('sequence')->get()
            : collect();
        $assignments = StudentTransportAssignment::query()->where('school_id', $user->school_id)
            ->when($isParent, fn ($query) => $query->whereIn('student_id', $visibleStudentIds))
            ->with(['student:id,name', 'route:id,name,code', 'stop:id,name'])->latest()->get();
        $guardians = AuthorizedPickupGuardian::query()->where('school_id', $user->school_id)
            ->when($isParent, fn ($query) => $query->whereIn('student_id', $visibleStudentIds))
            ->with('student:id,name')->latest()->get();
        $events = TransportEvent::query()->where('school_id', $user->school_id)
            ->when($isParent, fn ($query) => $query->whereIn('student_id', $visibleStudentIds))
            ->with(['student:id,name', 'route:id,name', 'stop:id,name', 'guardian:id,name'])->latest('occurred_at')->limit(100)->get();

        return view('livewire.transport.transport-hub', compact(
            'isParent', 'children', 'routes', 'students', 'assignmentStops', 'assignments', 'guardians', 'events'
        ))->layout('layouts.dashboard', [
            'breadcrumbs' => [
                ['href' => route('dashboard'), 'text' => 'Dashboard'],
                ['href' => route('transport.index'), 'text' => 'Transport & Pickup', 'active' => true],
            ],
        ])->title('Transport & Pickup');
    }
}
