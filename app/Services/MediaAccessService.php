<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\ParentRecord;
use App\Models\User;
use App\Support\ContentVisibility;

class MediaAccessService
{
    public function canView(?User $viewer, MediaAsset $asset): bool
    {
        if ($asset->visibility === ContentVisibility::PUBLIC) {
            return $asset->publication_approved_at !== null;
        }
        if (! $viewer || ! $this->hasPortalAccess($viewer) || (int) $viewer->school_id !== (int) $asset->school_id) {
            return false;
        }
        if ($viewer->id === $asset->uploaded_by || $viewer->can('moderate community content')) {
            return true;
        }
        if ($asset->visibility === ContentVisibility::SCHOOL) {
            return true;
        }
        if ($asset->subject_user_id && $viewer->id === $asset->subject_user_id) {
            return in_array($asset->visibility, [ContentVisibility::RECIPIENT, ContentVisibility::GUARDIANS], true);
        }
        if ($asset->visibility !== ContentVisibility::GUARDIANS || ! $asset->subject_user_id) {
            return false;
        }

        $links = ParentRecord::query()
            ->active()
            ->where('user_id', $viewer->id)
            ->where('student_id', $asset->subject_user_id)
            ->whereNotNull('verified_at');

        if ($asset->usage_area === 'portfolio') {
            $links->where('can_view_portfolio', true);
        }

        return $links->exists();
    }

    private function hasPortalAccess(User $viewer): bool
    {
        if ($viewer->locked && ! $viewer->hasAnyRole(['super-admin', 'super_admin'])) {
            return false;
        }

        return ! $viewer->requires_password_change
            && ! $viewer->hasRole('applicant')
            && ! $viewer->accountApplication()->exists();
    }
}
