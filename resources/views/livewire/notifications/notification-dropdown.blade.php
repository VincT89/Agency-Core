<div class="dropdown" x-data="{ open: false }" @click.outside="open = false">
  <button class="tb-btn" @click="open = !open">
    @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
      <div class="badge-notif">{{ $unreadNotificationsCount }}</div>
    @endif
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  </button>

  <div class="dropdown-menu dropdown-menu-wide" x-show="open" x-transition x-cloak>
    <div class="dropdown-header">
      <div class="dropdown-header-title">Notifiche</div>
      @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
        <div class="u-flex-center u-gap-md">
          <button type="button" wire:click="markAllAsRead" class="notif-mark-all" @click.stop>Segna tutte lette</button>
          <span class="notif-count-badge">{{ $unreadNotificationsCount }} nuove</span>
        </div>
      @endif
    </div>
    <div class="dropdown-body">
      @forelse($latestNotifications ?? [] as $notification)
        @php /** @var \Illuminate\Notifications\DatabaseNotification $notification */ @endphp
        <div class="notif-item {{ $notification->read_at ? '' : 'unread' }}">
          <button type="button" wire:click="markAsReadAndRedirect('{{ $notification->id }}')" class="notif-item-btn" @click.stop>
            @php
              $iconName = 'bell';
              $nType = $notification->data['type'] ?? '';
              if (in_array($nType, ['task_assigned', 'task_due_soon', 'task_created']))
                $iconName = 'check-square';
              elseif ($nType === 'ticket_assigned')
                $iconName = 'ticket';
              elseif (in_array($nType, ['invoice_overdue', 'payment_recorded']))
                $iconName = 'credit-card';
            @endphp
            <div class="notif-item-title">
              <i data-lucide="{{ $iconName }}" class="u-icon-sm notif-item-icon"></i>
              <span>{{ $notification->data['title'] ?? 'Nuova Notifica' }}</span>
            </div>
            <div class="notif-item-body">{{ $notification->data['message'] ?? '' }}</div>
            <div class="notif-item-time">{{ $notification->created_at->diffForHumans() }}</div>
          </button>
          <button type="button" wire:click="deleteNotification('{{ $notification->id }}')" class="notif-item-delete" title="Elimina notifica" @click.stop>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
            </svg>
          </button>
        </div>
      @empty
        <div class="notif-empty">
          <i data-lucide="inbox" class="u-icon-lg notif-empty-icon"></i>
          <div class="u-text-strong">Tutto tranquillo!</div>
          <div class="u-text-mono">Non hai nuove notifiche da leggere.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>
