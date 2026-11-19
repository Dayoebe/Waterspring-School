<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryImageStorage;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            'password' => [
                'required',
                'string',
                'min:8',
                Rule::notIn(['password', '12345678']),
                'different:current_password',
            ],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.required' => 'Enter a new password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.different' => 'The new password must be different from your current password.',
            'password.not_in' => 'Choose a stronger password. The default passwords "password" and "12345678" are not allowed.',
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
        $actor = $request->user();
        $request->merge(['username' => Username::normalize($request->input('username'))]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'username' => Username::rules($actor->id),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($actor->id)],
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
        ], [
            'username.min' => 'The username must be at least 3 characters.',
            'username.max' => 'The username must not be longer than 50 characters.',
            'username.regex' => 'The username may contain only letters, numbers, dots, underscores, and hyphens.',
            'username.unique' => 'That username is already in use. Choose another username.',
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('profile.edit').'#profile-information')
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        $validated = $validator->validated();
        $payload = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'email_is_placeholder' => false,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
        ];

        $oldProfilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $storage = app(CloudinaryImageStorage::class);
            $newPath = $storage->upload(
                $request->file('profile_photo'),
                $storage->folder('profile-photos', $actor->school_id)
            );

            $oldProfilePhotoPath = $actor->profile_photo_path;
            $payload['profile_photo_path'] = $newPath;
        }

        try {
            DB::connection($actor->getConnectionName())->transaction(function () use ($actor, $payload, $validated): void {
                $user = $actor->newQuery()->lockForUpdate()->findOrFail($actor->getKey());
                $user->forceFill($payload)->saveOrFail();

                if ($user->staffProfile) {
                    $user->staffProfile->forceFill([
                        'job_title' => ($validated['staff_job_title'] ?? null) ?: $user->staffProfile->job_title,
                        'bio' => $validated['staff_bio'] ?? null,
                        'qualifications' => $validated['staff_qualifications'] ?? null,
                        'responsibilities' => $validated['staff_responsibilities'] ?? null,
                    ])->saveOrFail();
                }

                $user->refresh();

                if ($user->username !== $validated['username']) {
                    throw new RuntimeException('The stored username did not match the submitted username.');
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->to(route('profile.edit').'#profile-information')
                ->withErrors([
                    'profile_save' => 'Your profile could not be saved. No account changes were applied. Please try again.',
                ], 'profile')
                ->withInput();
        }

        if ($oldProfilePhotoPath && str_starts_with($oldProfilePhotoPath, 'profile-photos/')) {
            Storage::disk('public')->delete($oldProfilePhotoPath);
        }

        return redirect()->to(route('profile.edit').'#profile-information')
            ->with('profile_success', 'Profile updated successfully. Your username is ready to use when signing in.');
    }
}
