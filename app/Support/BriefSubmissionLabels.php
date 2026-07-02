<?php

namespace App\Support;

class BriefSubmissionLabels
{
    /** @return array<string, string> */
    public static function forType(string $briefType): array
    {
        $templateLabels = BriefFormSchemaTemplates::labels();

        if (isset($templateLabels[$briefType])) {
            return $templateLabels[$briefType];
        }

        return [];
    }
}
