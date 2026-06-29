<?php

namespace App\Support;

class BriefSubmissionLabels
{
    /** @return array<string, string> */
    public static function forType(string $briefType): array
    {
        $labels = [
            'website' => [
                'client_name'         => "Client's Name",
                'project_name'        => 'Project Name',
                'site_type'           => 'New site or revamp',
                'site_type_other'     => 'Revamp / other details',
                'website_style'       => 'Website style',
                'main_content'        => 'Main content pages',
                'main_content_notes'  => 'Additional content notes',
                'color_preferences'   => 'Color preferences',
                'key_features'        => 'Key features / functionalities',
                'writing_style'       => 'Writing style',
                'target_audience'     => 'Target audience',
                'current_channels'    => 'Current sales / social channels',
                'reference_themes'    => 'Reference website themes / URLs',
                'liked_elements'      => 'Elements liked on other sites',
                'logo_status'         => 'Logo status',
                'has_domain'          => 'Domain name',
                'has_hosting'         => 'Hosting and email',
                'has_images'          => 'Images for website',
                'has_content'         => 'Website content',
            ],
            'logo' => [
                'name'               => 'Name',
                'email'              => 'Email',
                'logo_name'          => 'Logo name',
                'company_slogan'     => 'Company slogan',
                'description'        => 'Competitors reference',
                'bussiness_about'    => 'Business description',
                'additional_detail'  => 'Additional logo details',
                'primarycolor'       => 'Primary color',
                'secondarycolor'     => 'Secondary color',
                'person_name'        => 'Contact person name',
                'person_email'       => 'Contact person email',
                'designation'        => 'Designation',
                'person_phone'       => 'Contact phone',
                'company_address'    => 'Company address',
                'company_phone'      => 'Company phone',
            ],
            'ebook' => [
                'name'                => 'Name',
                'email'               => 'Email',
                'book_title'          => 'Book title',
                'book_subtitle'       => 'Book subtitle',
                'genre'               => 'Genre',
                'word_count'          => 'Word count',
                'synopsis'            => 'Synopsis',
                'target_audience'     => 'Target audience',
                'manuscript_status'   => 'Manuscript status',
                'formats_required'    => 'Formats required',
                'cover_requirements'  => 'Cover requirements',
                'isbn_required'       => 'ISBN required',
                'timeline'            => 'Timeline',
                'reference_books'     => 'Reference books',
                'additional_notes'    => 'Additional notes',
            ],
        ];

        return $labels[$briefType] ?? [];
    }
}
