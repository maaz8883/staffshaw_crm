<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SaleUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Sale $sale,
        public User $editor
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
        return [
            'kind'         => 'sale_updated',
            'sale_id'      => $this->sale->id,
            'title'        => $this->sale->title,
            'editor_name'  => $this->editor->name,
            'body'         => sprintf(
                '%s edited the sale "%s" (re-submitted for approval).',
                $this->editor->name,
                $this->sale->title
            ),
            'action_url'   => route('admin.sales.show', $this->sale),
        ];
    }
}
