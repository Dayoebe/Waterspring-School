<x-mail::message>
# @if($notificationType === 'approved') Admission Approved @elseif($notificationType === 'rejected') Admission Application Update @else Application Received @endif

Dear {{ $admission->guardian_name ?: $admission->student_name }},

@if($notificationType === 'approved')
We are pleased to confirm that **{{ $admission->student_name }}** has been offered admission to **{{ $admission->school?->name }}**.

<x-mail::panel>
**Application reference:** {{ $admission->reference_no }}  
**Admission number:** {{ $admission->enrolledStudentRecord?->admission_number }}  
**Class:** {{ $admission->myClass?->name }}{{ $admission->section?->name ? ' - '.$admission->section->name : '' }}  
@if(!$admission->enrolledUser?->email_is_placeholder)
**Portal email:** {{ $admission->enrolledUser?->email }}  
**Temporary password:** {{ $temporaryPassword }}
@else
**Portal access:** The school will confirm the student’s login email and secure password separately.
@endif
</x-mail::panel>

@if(!$admission->enrolledUser?->email_is_placeholder)
Please sign in to the school portal with these details and change the password after the first login. Keep the login details private.

<x-mail::button :url="route('login')">
Sign in to the Student Portal
</x-mail::button>
@else
The application did not include a student email address, so temporary internal credentials have not been shown. Please contact the school if you need an update about portal access.
@endif
@elseif($notificationType === 'rejected')
Thank you for applying to **{{ $admission->school?->name }}** on behalf of **{{ $admission->student_name }}**.

After reviewing the application, we are unable to offer admission at this time. This decision applies to application **{{ $admission->reference_no }}**. You may contact the school if you need clarification about the next available admission opportunity.
@else
Thank you for submitting an admission application for **{{ $admission->student_name }}** to **{{ $admission->school?->name }}**.

We have received the application successfully. Our admissions team will review the information and contact you when a decision or further information is available.

<x-mail::panel>
**Application reference:** {{ $admission->reference_no }}  
**Class applied for:** {{ $admission->myClass?->name }}{{ $admission->section?->name ? ' - '.$admission->section->name : '' }}  
**Status:** Pending review
</x-mail::panel>

Please keep the application reference for future communication.
@endif

Regards,  
Admissions Team  
{{ $admission->school?->name }}
</x-mail::message>
