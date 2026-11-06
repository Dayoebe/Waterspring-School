<?php

namespace App\Http\Controllers;

use App\Mail\AdmissionNotificationMail;
use App\Models\AdmissionRegistration;
use App\Services\AdmissionPipeline;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AdmissionOfferController extends Controller
{
    public function show(string $reference, string $token): View
    {
        $admission = AdmissionRegistration::query()->with('school')->where('reference_no', $reference)->firstOrFail();
        $valid = $this->tokenIsValid($admission, $token);

        return view('admissions.offer', compact('admission', 'token', 'valid'));
    }

    public function accept(Request $request, AdmissionPipeline $pipeline, string $reference, string $token): RedirectResponse
    {
        $admission = AdmissionRegistration::query()->with(['school', 'myClass', 'section'])->where('reference_no', $reference)->firstOrFail();
        $validated = $request->validate(['accepted_by_name' => ['required', 'string', 'max:255']]);

        try {
            $pipeline->acceptOffer($admission, $token, $validated['accepted_by_name'], $request->ip());
            foreach ($admission->notificationEmails() as $email) {
                Mail::to($email)->send(new AdmissionNotificationMail($admission->fresh(), 'accepted'));
            }
        } catch (DomainException $exception) {
            return back()->withErrors(['offer' => $exception->getMessage()]);
        }

        return redirect()->route('admission.offer.show', [$reference, $token])
            ->with('success', 'The admission offer has been accepted. The school will confirm the next steps.');
    }

    private function tokenIsValid(AdmissionRegistration $admission, string $token): bool
    {
        return $admission->status === 'offered'
            && $admission->acceptance_token_hash
            && ! $admission->offer_expires_at?->isPast()
            && hash_equals($admission->acceptance_token_hash, hash('sha256', $token));
    }
}
