<?php

namespace App\MediaLibrary;

use App\Models\LoanApplication;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    /**
     * Get the base folder path (Member No / Loan App No).
     */
    protected function getBasePath(Media $media): string
    {
        $model = $media->model;

        if ($model instanceof LoanApplication) {
            $memberNo = $model->member?->member_no ?? 'MEMBER-GENERAL';
            $appNo = $model->application_no ?? 'LN-GENERAL';

            $safeMemberNo = str_replace(['/', '\\', ' '], '-', $memberNo);
            $safeAppNo = str_replace(['/', '\\', ' '], '-', $appNo);

            return "{$safeMemberNo}/{$safeAppNo}/";
        }

        return 'general_documents/';
    }

    /**
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media);
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . 'conversions/';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . 'responsive-images/';
    }
}
