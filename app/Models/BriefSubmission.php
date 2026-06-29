<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class BriefSubmission
{
    public const STATUS_PENDING   = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public function __construct(
        public ?int $id = null,
        public ?int $sale_id = null,
        public ?string $brief_type = null,
        public ?string $form_path = null,
        /** @var array<string, mixed> */
        public array $data = [],
        /** @var list<array<string, mixed>> */
        public array $attachments = [],
        public string $status = self::STATUS_SUBMITTED,
        public ?CarbonInterface $submitted_at = null,
        public ?string $client_name = null,
        public ?string $client_email = null,
        public ?string $client_ip = null,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromApi(array $row): self
    {
        $data = $row['data'] ?? [];
        $attachments = $row['attachments'] ?? [];
        $submittedAt = $row['submitted_at'] ?? null;

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            sale_id: isset($row['sale_id']) ? (int) $row['sale_id'] : null,
            brief_type: isset($row['brief_type']) ? (string) $row['brief_type'] : null,
            form_path: isset($row['form_path']) ? (string) $row['form_path'] : null,
            data: is_array($data) ? $data : [],
            attachments: is_array($attachments) ? $attachments : [],
            status: (string) ($row['status'] ?? self::STATUS_SUBMITTED),
            submitted_at: is_string($submittedAt) && $submittedAt !== ''
                ? Carbon::parse($submittedAt)
                : null,
            client_name: isset($row['client_name']) ? (string) $row['client_name'] : null,
            client_email: isset($row['client_email']) ? (string) $row['client_email'] : null,
            client_ip: isset($row['client_ip']) ? (string) $row['client_ip'] : null,
        );
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
