<?php

namespace App\Notifications;

use App\Models\TeamMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamJoinRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamMembership $membership)
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
        $this->membership->loadMissing(['user', 'team']);

        return [
            'kind'       => 'team_join_requested',
            'team_id'    => $this->membership->team_id,
            'user_id'    => $this->membership->user_id,
            'body'       => sprintf(
                '%s requested to join your team "%s" as a Project Manager.',
                $this->membership->user?->name ?? 'A user',
                $this->membership->team?->name ?? 'your team'
            ),
            'action_url' => route('admin.teams.join-requests.index'),
        ];
    }
}
