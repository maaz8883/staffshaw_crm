<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SaleDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Sale $sale,
        public User $decider,
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
        $verb = $this->approved ? 'approved' : 'rejected';

        return [
            'kind'        => 'sale_decision',
            'sale_id'     => $this->sale->id,
            'title'       => $this->sale->title,
            'approved'    => $this->approved,
            'decider_name'=> $this->decider->name,
            'body'        => sprintf(
                '%s %s the sale "%s".',
                $this->decider->name,
                $verb,
                $this->sale->title
            ),
            'action_url'  => route('admin.sales.show', $this->sale),
        ];
    }
}
