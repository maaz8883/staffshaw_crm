<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public ?User $assignedBy = null)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'       => 'lead_assigned',
            'lead_id'    => $this->lead->id,
            'title'      => $this->lead->name,
            'body'       => sprintf(
                '%s assigned you a new lead: %s%s.',
                $this->assignedBy?->name ?? 'Someone',
                $this->lead->name,
                $this->lead->company ? ' ('.$this->lead->company.')' : ''
            ),
            'action_url' => route('admin.leads.show', $this->lead),
        ];
    }
}
