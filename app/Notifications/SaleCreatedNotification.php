<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SaleCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Sale $sale)
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
        $this->sale->loadMissing('user');

        return [
            'kind'        => 'sale_created',
            'sale_id'     => $this->sale->id,
            'title'       => $this->sale->title,
            'agent_name'  => $this->sale->user?->name ?? 'Agent',
            'body'        => sprintf(
                '%s submitted a new sale "%s" for approval.',
                $this->sale->user?->name ?? 'An agent',
                $this->sale->title
            ),
            'action_url'  => route('admin.sales.show', $this->sale),
        ];
    }
}
