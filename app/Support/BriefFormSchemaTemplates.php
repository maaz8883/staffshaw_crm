<?php

namespace App\Support;

class BriefFormSchemaTemplates
{
    /** @return array<string, array<string, string>> */
    public static function labels(): array
    {
        return [
            'website' => [
                'client_name'        => "Client's Name",
                'project_name'       => 'Project Name',
                'site_type'          => 'Will the website be a completely new site or will it be a redesign of an existing site?',
                'site_type_other'    => "In case of website revamp, elaborate the changes that you would like to be implemented in 'other section'.",
                'website_style'      => 'Describe the website style that you are looking for.',
                'main_content'       => 'What is the main content you plan to have on your website?',
                'main_content_notes' => 'Additional content notes',
                'color_preferences'  => 'Do you have any color preferences?',
                'key_features'       => 'Are there any key features or functionalities you want to include?',
                'writing_style'      => 'What kind of writing style do you prefer for your blogs or website in general?',
                'target_audience'    => 'Please describe your target customers or the audience you intend to reach via your website.',
                'current_channels'   => 'How do they buy or acquire knowledge about your products or services at the moment?',
                'reference_themes'   => 'Do you have any specific website themes or URLs in mind?',
                'liked_elements'     => 'Is there anything you have seen on other sites that you really like and want to include in the design?',
                'logo_status'        => 'Do you have a logo? If yes, please upload your logo source files below.',
                'logo_file'          => 'Logo source files (optional)',
                'has_domain'         => 'Do you already have a domain name?',
                'has_hosting'        => 'Do you already have hosting and email accounts?',
                'has_images'         => 'Do you have images that you would like to be used on your website?',
                'image_files'        => 'Website images (optional)',
                'has_content'        => 'Do you have content for your website?',
                'content_files'      => 'Content files e.g. About the Author (optional)',
            ],
            'logo' => [
                'name'              => 'Name',
                'email'             => 'Email',
                'logo_name'         => 'Logo name',
                'company_slogan'    => 'Company slogan',
                'description'       => 'Competitors reference',
                'bussiness_about'   => 'Business description',
                'additional_detail' => 'Additional logo details',
                'primarycolor'      => 'Primary color',
                'secondarycolor'    => 'Secondary color',
                'person_name'       => 'Contact person name',
                'person_email'      => 'Contact person email',
                'designation'       => 'Designation',
                'person_phone'      => 'Contact phone',
                'company_address'   => 'Company address',
                'company_phone'     => 'Company phone',
            ],
            'ebook' => [
                'name'               => 'Name',
                'email'              => 'Email',
                'book_title'         => 'Book title',
                'book_subtitle'      => 'Book subtitle',
                'genre'              => 'Genre',
                'word_count'         => 'Word count',
                'synopsis'           => 'Synopsis',
                'target_audience'    => 'Target audience',
                'manuscript_status'  => 'Manuscript status',
                'formats_required'   => 'Formats required',
                'cover_requirements' => 'Cover requirements',
                'isbn_required'      => 'ISBN required',
                'timeline'           => 'Timeline',
                'reference_books'    => 'Reference books',
                'additional_notes'   => 'Additional notes',
            ],
            'book_cover' => [
                'book_title'             => 'Book Title',
                'subtitle'               => 'Subtitle',
                'author_name'            => "Author's Name",
                'cover_idea'             => 'What idea do you have behind your book cover?',
                'cover_style_type'       => 'Are you looking for a straight drawing, sketch, painting, illustration or business type cover?',
                'mood_business'          => 'Business/Professional',
                'mood_motivational'      => 'Motivational',
                'mood_energetic'         => 'Energetic',
                'mood_family'            => 'Family-oriented',
                'mood_humor'             => 'Humor/Fun',
                'mood_informative'       => 'Informative/Knowledgeable',
                'mood_sophisticated'     => 'Sophisticated',
                'mood_whimsical'         => 'Whimsical',
                'mood_other'             => 'Other',
                'name_placement_details' => 'Details of Name Placement, Font, Logos etc.',
                'extra_cover_content'    => "Do you want any sort of content other than the book title and the author's name on the book cover?",
                'format_ebook'           => 'eBook',
                'format_paperback'       => 'Paperback',
                'format_hardcover'       => 'Hardcover',
                'approx_pages'           => 'Approximate Number of Pages',
                'cover_size'             => 'Preferable Cover Size',
                'publishing_date'        => 'Publishing Date',
            ],
        ];
    }

    /** @return list<string> */
    public static function requiredFields(string $slug): array
    {
        return match ($slug) {
            'website' => ['client_name', 'project_name', 'site_type', 'website_style', 'main_content', 'color_preferences', 'key_features', 'writing_style', 'target_audience'],
            'logo'    => ['name', 'email', 'logo_name', 'description', 'bussiness_about', 'additional_detail'],
            'ebook'      => ['name', 'email', 'book_title', 'genre', 'synopsis', 'target_audience', 'manuscript_status', 'formats_required'],
            'book_cover' => ['book_title', 'author_name', 'cover_idea', 'cover_style_type', 'approx_pages', 'cover_size', 'publishing_date'],
            default      => [],
        };
    }

    public static function defaultSchemaForSlug(string $slug): ?array
    {
        return match ($slug) {
            'website' => self::websiteSchema(),
            'logo'    => self::logoSchema(),
            'ebook'      => self::ebookSchema(),
            'book_cover' => self::bookCoverSchema(),
            'custom', 'brief' => self::customSchema(),
            default   => self::customSchema(),
        };
    }

    /** @param array<string, mixed> $extra */
    private static function field(string $id, string $type, string $label, bool $required = false, array $extra = []): array
    {
        return array_merge([
            'id'       => $id,
            'type'     => $type,
            'label'    => $label,
            'required' => $required,
        ], $extra);
    }

    private static function websiteSchema(): array
    {
        $req = array_flip(self::requiredFields('website'));
        $L = self::labels()['website'];

        return [
            'version'  => 3,
            'template' => 'website',
            'title'    => 'Website Brief Form',
            'sections' => [
                [
                    'id'     => 'client_info',
                    'title'  => "Client's Information",
                    'fields' => [
                        self::field('client_name', 'text', $L['client_name'], isset($req['client_name'])),
                        self::field('project_name', 'text', $L['project_name'], isset($req['project_name'])),
                    ],
                ],
                [
                    'id'     => 'requirements',
                    'title'  => 'Understanding Your Website Requirements',
                    'fields' => [
                        self::field('site_type', 'radio', $L['site_type'], isset($req['site_type']), [
                            'options' => [
                                ['value' => 'new', 'label' => 'New'],
                                ['value' => 'revamp', 'label' => 'Revamp'],
                                ['value' => 'other', 'label' => 'Other'],
                            ],
                        ]),
                        self::field('site_type_other', 'textarea', $L['site_type_other']),
                        self::field('website_style', 'textarea', $L['website_style'], isset($req['website_style']), [
                            'help' => '(e.g., Clean and Minimalist, Bold and Vibrant)',
                        ]),
                        self::field('main_content', 'textarea', $L['main_content'], isset($req['main_content']), [
                            'help' => '(e.g., Home, About Us, Services, Products, Contact, Blog, etc.)',
                        ]),
                        self::field('color_preferences', 'textarea', $L['color_preferences'], isset($req['color_preferences']), [
                            'help' => 'i.e. colors that you want to use in the design?',
                        ]),
                        self::field('color_palette_file', 'file', 'Color palette / reference photo (optional)', false, [
                            'accept' => 'image/*,.pdf',
                        ]),
                        self::field('key_features', 'textarea', $L['key_features'], isset($req['key_features']), [
                            'help' => '(e.g., contact form, photo gallery, e-commerce integration, social media links.)',
                        ]),
                        self::field('writing_style', 'textarea', $L['writing_style'], isset($req['writing_style']), [
                            'help' => '(e.g., Formal and Professional, Informal/Laid back, Hip and trendy.)',
                        ]),
                    ],
                ],
                [
                    'id'     => 'audience',
                    'title'  => 'Understanding Your Audience/Customers',
                    'fields' => [
                        self::field('target_audience', 'textarea', $L['target_audience'], isset($req['target_audience']), [
                            'help' => '(For example: are they primarily other businesses, special interest groups, consumers, their interests, age, gender)?',
                        ]),
                        self::field('current_channels', 'textarea', $L['current_channels'], false, [
                            'help' => 'Please share the links below so we can add them to the website. We would appreciate it if you could also provide your social media links (if applicable) below.',
                        ]),
                    ],
                ],
                [
                    'id'     => 'competitors',
                    'title'  => 'Understanding Your Competitors',
                    'fields' => [
                        self::field('reference_themes', 'textarea', $L['reference_themes'], false, [
                            'help' => 'If so, please share them.',
                        ]),
                        self::field('liked_elements', 'textarea', $L['liked_elements'], false, [
                            'help' => 'If so, please elaborate with examples.',
                        ]),
                        self::field('reference_files', 'file', 'Reference attachments (optional)', false, [
                            'accept'   => 'image/*,.pdf',
                            'multiple' => true,
                        ]),
                    ],
                ],
                [
                    'id'     => 'details',
                    'title'  => 'Website Details',
                    'fields' => [
                        self::field('logo_status', 'textarea', $L['logo_status']),
                        self::field('logo_file', 'file', $L['logo_file'], false, [
                            'accept' => 'image/*,.pdf,.ai,.svg',
                        ]),
                        self::field('has_domain', 'textarea', $L['has_domain'], false, [
                            'help' => 'If yes, please mention below.',
                        ]),
                        self::field('has_hosting', 'textarea', $L['has_hosting'], false, [
                            'help' => 'If yes, please state the service provider and hosting package.',
                        ]),
                        self::field('has_images', 'textarea', $L['has_images'], false, [
                            'help' => 'If yes, please upload your images in high resolution using the field below.',
                        ]),
                        self::field('image_files', 'file', $L['image_files'], false, [
                            'accept'   => 'image/*,.pdf',
                            'multiple' => true,
                        ]),
                        self::field('has_content', 'textarea', $L['has_content'], false, [
                            'help' => 'If yes, please upload your content as an editable Word document (or PDF) using the field below.',
                        ]),
                        self::field('content_files', 'file', $L['content_files'], false, [
                            'accept'   => '.pdf,.doc,.docx',
                            'multiple' => true,
                        ]),
                    ],
                ],
            ],
        ];
    }

    private static function logoSchema(): array
    {
        $req = array_flip(self::requiredFields('logo'));
        $L = self::labels()['logo'];

        return [
            'version'  => 1,
            'template' => 'logo',
            'title'    => 'Logo Brief',
            'sections' => [
                [
                    'id'     => 'main',
                    'title'  => 'Logo Brief',
                    'fields' => [
                        self::field('name', 'text', $L['name'], isset($req['name'])),
                        self::field('email', 'email', $L['email'], isset($req['email'])),
                        self::field('logo_name', 'text', $L['logo_name'], isset($req['logo_name'])),
                        self::field('company_slogan', 'text', $L['company_slogan']),
                        self::field('description', 'textarea', $L['description'], isset($req['description'])),
                        self::field('bussiness_about', 'textarea', $L['bussiness_about'], isset($req['bussiness_about'])),
                        self::field('additional_detail', 'textarea', $L['additional_detail'], isset($req['additional_detail'])),
                        self::field('primarycolor', 'text', $L['primarycolor']),
                        self::field('secondarycolor', 'text', $L['secondarycolor']),
                        self::field('person_name', 'text', $L['person_name']),
                        self::field('person_email', 'email', $L['person_email']),
                        self::field('designation', 'text', $L['designation']),
                        self::field('person_phone', 'tel', $L['person_phone']),
                        self::field('company_address', 'textarea', $L['company_address']),
                        self::field('company_phone', 'tel', $L['company_phone']),
                        self::field('logo_file', 'file', 'Reference logo file (optional)', false, [
                            'accept' => 'image/*,.pdf,.ai,.svg',
                        ]),
                    ],
                ],
            ],
        ];
    }

    private static function customSchema(): array
    {
        return [
            'version'  => 1,
            'template' => 'custom',
            'title'    => 'Brief Form',
            'sections' => [
                [
                    'id'     => 'main',
                    'title'  => 'Main',
                    'fields' => [],
                ],
            ],
        ];
    }

    private static function ebookSchema(): array
    {
        $req = array_flip(self::requiredFields('ebook'));
        $L = self::labels()['ebook'];

        return [
            'version'  => 1,
            'template' => 'ebook',
            'title'    => 'Ebook Brief',
            'sections' => [
                [
                    'id'     => 'main',
                    'title'  => 'Ebook Brief',
                    'fields' => [
                        self::field('name', 'text', $L['name'], isset($req['name'])),
                        self::field('email', 'email', $L['email'], isset($req['email'])),
                        self::field('book_title', 'text', $L['book_title'], isset($req['book_title'])),
                        self::field('book_subtitle', 'text', $L['book_subtitle']),
                        self::field('genre', 'text', $L['genre'], isset($req['genre'])),
                        self::field('word_count', 'text', $L['word_count']),
                        self::field('synopsis', 'textarea', $L['synopsis'], isset($req['synopsis'])),
                        self::field('target_audience', 'textarea', $L['target_audience'], isset($req['target_audience'])),
                        self::field('manuscript_status', 'text', $L['manuscript_status'], isset($req['manuscript_status'])),
                        self::field('formats_required', 'text', $L['formats_required'], isset($req['formats_required']), [
                            'placeholder' => 'PDF, ePub, Mobi, Print-ready',
                        ]),
                        self::field('cover_requirements', 'textarea', $L['cover_requirements']),
                        self::field('isbn_required', 'text', $L['isbn_required']),
                        self::field('timeline', 'text', $L['timeline']),
                        self::field('reference_books', 'textarea', $L['reference_books']),
                        self::field('additional_notes', 'textarea', $L['additional_notes']),
                        self::field('manuscript_file', 'file', 'Manuscript sample (optional)', false, [
                            'accept' => '.pdf,.doc,.docx',
                        ]),
                    ],
                ],
            ],
        ];
    }

    private static function bookCoverSchema(): array
    {
        $req = array_flip(self::requiredFields('book_cover'));
        $L = self::labels()['book_cover'];

        return [
            'version'     => 2,
            'template'    => 'book_cover',
            'title'       => 'Book Cover Design Brief',
            'description' => 'To help us create the best design for you, your book, and your readers, please answer the following questions as best as you can.',
            'sections'    => [
                [
                    'id'     => 'book_info',
                    'title'  => 'Book Information',
                    'fields' => [
                        self::field('book_title', 'text', $L['book_title'], isset($req['book_title'])),
                        self::field('subtitle', 'text', $L['subtitle']),
                        self::field('author_name', 'text', $L['author_name'], isset($req['author_name'])),
                    ],
                ],
                [
                    'id'     => 'cover_concept',
                    'title'  => 'Cover Concept',
                    'fields' => [
                        self::field('cover_idea', 'textarea', $L['cover_idea'], isset($req['cover_idea'])),
                        self::field('cover_style_type', 'textarea', $L['cover_style_type'], isset($req['cover_style_type'])),
                    ],
                ],
                [
                    'id'     => 'mood',
                    'title'  => 'What impression or mood do you want to convey to the reader?',
                    'help'   => 'Check all that apply.',
                    'fields' => [
                        self::field('mood_business', 'checkbox', $L['mood_business']),
                        self::field('mood_motivational', 'checkbox', $L['mood_motivational']),
                        self::field('mood_energetic', 'checkbox', $L['mood_energetic']),
                        self::field('mood_family', 'checkbox', $L['mood_family']),
                        self::field('mood_humor', 'checkbox', $L['mood_humor']),
                        self::field('mood_informative', 'checkbox', $L['mood_informative']),
                        self::field('mood_sophisticated', 'checkbox', $L['mood_sophisticated']),
                        self::field('mood_whimsical', 'checkbox', $L['mood_whimsical']),
                        self::field('mood_other', 'text', $L['mood_other'], false, [
                            'placeholder' => 'If other, please specify',
                        ]),
                    ],
                ],
                [
                    'id'     => 'details',
                    'title'  => 'Cover Details',
                    'fields' => [
                        self::field('name_placement_details', 'textarea', $L['name_placement_details'], false, [
                            'help' => 'If applicable',
                        ]),
                        self::field('extra_cover_content', 'textarea', $L['extra_cover_content']),
                    ],
                ],
                [
                    'id'     => 'format',
                    'title'  => 'Book Format',
                    'help'   => 'Check all that apply.',
                    'fields' => [
                        self::field('format_ebook', 'checkbox', $L['format_ebook']),
                        self::field('format_paperback', 'checkbox', $L['format_paperback']),
                        self::field('format_hardcover', 'checkbox', $L['format_hardcover']),
                        self::field('approx_pages', 'number', $L['approx_pages'], isset($req['approx_pages'])),
                        self::field('cover_size', 'text', $L['cover_size'], isset($req['cover_size'])),
                        self::field('publishing_date', 'text', $L['publishing_date'], isset($req['publishing_date']), [
                            'placeholder' => 'Example: January 7, 2019',
                        ]),
                    ],
                ],
            ],
        ];
    }
}
