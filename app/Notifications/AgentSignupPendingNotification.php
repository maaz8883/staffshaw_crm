<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentSignupPendingNotification extends Notification
{
    use Queueable;

    public function __construct(public User $newUser)
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
        $this->newUser->loadMissing('team');

        return [
            'kind'        => 'agent_signup_pending',
            'user_id'     => $this->newUser->id,
            'body'        => sprintf(
                'New agent signup: %s (%s) requested to join team "%s".',
                $this->newUser->name,
                $this->newUser->email,
                $this->newUser->team?->name ?? '—'
            ),
            'action_url'  => route('admin.pending-registrations.index'),
        ];
    }
}
