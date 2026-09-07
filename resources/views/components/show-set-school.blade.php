@hasanyrole('super-admin|super_admin')
<div class="dashboard-school-context">
    <i class="fas fa-school" aria-hidden="true"></i>
    <div>
        <span class="dashboard-school-label">School of operation</span>
        @if (auth()->user()->school)
            <span class="dashboard-school-name">{{ auth()->user()->school->name }}</span>
        @else
            <a href="{{ route('schools.index') }}">Choose a school to get started</a>
        @endif
    </div>
</div>
@endhasanyrole
