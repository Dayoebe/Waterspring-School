<?php

namespace App\Services;

use App\Mail\StudentSupportCaseMail;
use App\Models\StudentSupportCase;
use App\Models\StudentSupportMailDelivery;
use Illuminate\Support\Facades\Mail;

class StudentSupportNotificationService
{
    public function notifyBoth(StudentSupportCase $case, string $event, string $eventKey): array
    {
        $case->loadMissing(['student', 'parent', 'leadTeacher']);
        $sent = 0; $failed = 0; $skipped = 0;
        foreach (collect([$case->parent, $case->leadTeacher])->filter()->unique('id') as $recipient) {
            $key = implode(':', ['support', $case->id, $event, $eventKey, $recipient->id]);
            $delivery = StudentSupportMailDelivery::firstOrCreate(
                ['deduplication_key' => $key],
                ['student_support_case_id' => $case->id, 'recipient_id' => $recipient->id, 'event' => $event, 'status' => 'pending']
            );
            if (! $delivery->wasRecentlyCreated) { $skipped++; continue; }
            if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                $delivery->update(['status' => 'failed', 'error' => 'Recipient has no valid email address.']); $failed++; continue;
            }
            try {
                Mail::to($recipient->email)->send(new StudentSupportCaseMail($case, $recipient, $event));
                $delivery->update(['status' => 'sent', 'sent_at' => now()]); $sent++;
            } catch (\Throwable $exception) {
                $delivery->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
                report($exception); $failed++;
            }
        }

        return compact('sent', 'failed', 'skipped');
    }
}
