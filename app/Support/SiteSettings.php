<?php

namespace App\Support;

use App\Models\School;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SiteSettings
{
    /** @var array<string, array<string, mixed>> */
    protected static array $settingsCache = [];

    protected static ?School $resolvedSchool = null;

    protected static bool $schoolResolved = false;

    public static function forPublicSite(): array
    {
        $settings = config('public-school');
        foreach (['logo_url', 'favicon_url'] as $key) {
            $settings['theme'][$key] = asset($settings['theme'][$key]);
        }

        return $settings;
    }

    public static function defaults(): array
    {
        return self::forPublicSite();
    }

    public static function resolveSchool(?Request $request = null): ?School
    {
        if (self::$schoolResolved) {
            return self::$resolvedSchool;
        }

        $request ??= app()->bound('request') ? request() : null;

        $school = null;

        if ($request) {
            $querySchool = trim((string) ($request->query('school') ?? ''));
            $querySchoolId = trim((string) ($request->query('school_id') ?? ''));

            if ($querySchool !== '') {
                $school = self::findSchoolByIdentifier($querySchool);
            } elseif ($querySchoolId !== '') {
                $school = self::findSchoolByIdentifier($querySchoolId);
            }

            if ($school && $request->hasSession()) {
                $request->session()->put('public_school_id', $school->id);
            }

            if (!$school && $request->hasSession()) {
                $sessionSchoolId = $request->session()->get('public_school_id');
                if ($sessionSchoolId) {
                    $school = School::query()->find((int) $sessionSchoolId);
                }
            }
        }

        if (!$school && Auth::check() && Auth::user()?->school_id) {
            $school = School::query()->find((int) Auth::user()->school_id);
        }

        self::$resolvedSchool = $school;
        self::$schoolResolved = true;

        return self::$resolvedSchool;
    }

    public static function forSchool(?int $schoolId = null): array
    {
        $cacheKey = $schoolId ? 'school:' . $schoolId : 'general';

        if (array_key_exists($cacheKey, self::$settingsCache)) {
            return self::$settingsCache[$cacheKey];
        }

        $settings = self::defaults();

        if (Schema::hasTable('site_settings')) {
            $general = SiteSetting::query()
                ->where('scope_key', SiteSetting::generalScopeKey())
                ->first()?->settings;

            if (is_array($general)) {
                $settings = array_replace_recursive($settings, $general);
            }

            if ($schoolId) {
                $schoolScoped = SiteSetting::query()
                    ->where('scope_key', SiteSetting::schoolScopeKey($schoolId))
                    ->first()?->settings;

                if (is_array($schoolScoped)) {
                    $settings = array_replace_recursive($settings, $schoolScoped);
                }
            }
        }

        if ($schoolId && Schema::hasTable('schools')) {
            $school = School::query()->find($schoolId);
            $settings = self::withSchoolFallbacks($settings, $school);
        }

        self::$settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public static function clearCache(): void
    {
        self::$settingsCache = [];
        self::$resolvedSchool = null;
        self::$schoolResolved = false;
    }

    protected static function findSchoolByIdentifier(string $identifier): ?School
    {
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return School::query()->find((int) $identifier);
        }

        return School::query()->where('code', $identifier)->first();
    }

    protected static function withSchoolFallbacks(array $settings, ?School $school): array
    {
        if (!$school) {
            return $settings;
        }

        if (($settings['school_name'] ?? '') === '') {
            $settings['school_name'] = (string) $school->name;
        }

        if (($settings['contact']['address'] ?? '') === '' && $school->address) {
            $settings['contact']['address'] = (string) $school->address;
        }

        if (($settings['contact']['phone_primary'] ?? '') === '' && $school->phone) {
            $settings['contact']['phone_primary'] = (string) $school->phone;
        }

        if (($settings['contact']['email'] ?? '') === '' && $school->email) {
            $settings['contact']['email'] = (string) $school->email;
        }

        if (($settings['home']['hero_title'] ?? '') === '') {
            $settings['home']['hero_title'] = (string) $school->name;
        }

        if (($settings['about_page']['hero_title'] ?? '') === '') {
            $settings['about_page']['hero_title'] = 'About ' . $school->name;
        }

        if (($settings['theme']['logo_url'] ?? '') === '' && !empty($school->logo_url)) {
            $settings['theme']['logo_url'] = (string) $school->logo_url;
        }

        if (($settings['meta']['author'] ?? '') === '') {
            $settings['meta']['author'] = (string) $school->name;
        }

        return $settings;
    }
}
