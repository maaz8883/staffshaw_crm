<?php

namespace App\Notifications;

use App\Models\TeamPpcSpending;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PpcSpendingAddedNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamPpcSpending $spending)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->spending->loadMissing(['user', 'team']);

        $monthLabel = \DateTime::createFromFormat('!m', $this->spending->month)->format('F')
            . ' ' . $this->spending->year;

        return [
            'kind'       => 'ppc_spending_added',
            'spending_id' => $this->spending->id,
            'team_name'  => $this->spending->team?->name ?? '—',
            'user_name'  => $this->spending->user?->name ?? 'PPC User',
            'amount'     => $this->spending->amount,
            'month'      => $monthLabel,
            'body'       => sprintf(
                '%s added $%s PPC spending for %s (%s).',
                $this->spending->user?->name ?? 'PPC User',
                number_format($this->spending->amount, 2),
                $this->spending->team?->name ?? '—',
                $monthLabel
            ),
            'action_url' => route('admin.ppc.index'),
        ];
    }
}
