<?php

namespace App\Notifications;

use App\Models\TeamMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamJoinDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeamMembership $membership,
        public bool $approved
    ) {
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
        $this->membership->loadMissing('team');
        $teamName = $this->membership->team?->name ?? 'the team';

        return [
            'kind'       => 'team_join_decision',
            'team_id'    => $this->membership->team_id,
            'approved'   => $this->approved,
            'body'       => $this->approved
                ? "Your request to join \"{$teamName}\" has been approved. You now have access to this team's data."
                : "Your request to join \"{$teamName}\" has been rejected.",
            'action_url' => route('admin.dashboard'),
        ];
    }
}
