<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryImageStorage;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

// Import Rule for validation

class ProfileController extends Controller
{
    /**
     * Show the form for changing the user's password.
     *
     * @return View
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Handle the password change request.
     *
     * @return RedirectResponse
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'different:current_password'],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.required' => 'Enter a new password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.different' => 'The new password must be different from your current password.',
            'password_confirmation.required' => 'Confirm your new password.',
            'password_confirmation.same' => 'The password confirmation does not match the new password.',
        ]);

        $actor = $request->user();

        try {
            DB::connection($actor->getConnectionName())->transaction(function () use ($actor, $validated): void {
                $user = $actor->newQuery()->lockForUpdate()->findOrFail($actor->getKey());

                // Recheck inside the lock so a concurrent password update cannot be overwritten.
                if (! Hash::check($validated['current_password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'current_password' => 'The current password changed before this request completed. Please try again.',
                    ]);
                }

                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'requires_password_change' => false,
                ])->saveOrFail();

                $user->refresh();

                if (! Hash::check($validated['password'], $user->password)) {
                    throw new RuntimeException('The stored password could not be verified after saving.');
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'password' => 'The new password could not be saved. Your existing password is still active. Please try again.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->to(route('profile.edit').'#password')
            ->with('success', 'Password changed successfully. You can now use your new password to sign in.');
    }

    /**
     * Show the form for editing the user's profile.
     *
     * @return View
     */
    public function edit()
    {
        return view('profile.edit', [
            'user' => auth()->user()?->loadMissing('roles:id,name', 'school:id,name,code', 'staffProfile.department'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * @return RedirectResponse
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        $request->merge(['username' => Username::normalize($request->input('username'))]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => Username::rules($user->id),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', 'string', 'max:12'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
            'staff_job_title' => ['nullable', 'string', 'max:255'],
            'staff_bio' => ['nullable', 'string', 'max:5000'],
            'staff_qualifications' => ['nullable', 'string', 'max:3000'],
            'staff_responsibilities' => ['nullable', 'string', 'max:3000'],
        ]);

        $payload = [
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'email_is_placeholder' => false,
            'phone' => $request->phone,
            'gender' => $request->gender,
            'birthday' => $request->birthday ?: null,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'nationality' => $request->nationality,
            'religion' => $request->religion,
            'blood_group' => $request->blood_group,
        ];

        $oldProfilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $storage = app(CloudinaryImageStorage::class);
            $newPath = $storage->upload(
                $request->file('profile_photo'),
                $storage->folder('profile-photos', $user->school_id)
            );

            $oldProfilePhotoPath = $user->profile_photo_path;
            $payload['profile_photo_path'] = $newPath;
        }

        $user->forceFill($payload)->save();
        if ($oldProfilePhotoPath && str_starts_with($oldProfilePhotoPath, 'profile-photos/')) {
            Storage::disk('public')->delete($oldProfilePhotoPath);
        }

        if ($user->staffProfile) {
            $user->staffProfile->update([
                'job_title' => $request->staff_job_title ?: $user->staffProfile->job_title,
                'bio' => $request->staff_bio,
                'qualifications' => $request->staff_qualifications,
                'responsibilities' => $request->staff_responsibilities,
            ]);
        }

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}
