<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Sodano Consulting' }}</title>
  <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles
</head>

<body>

  {{-- Background Canvas --}}
  <canvas id="bg-canvas" aria-hidden="true"
    style="position:fixed;inset:0;z-index:0;pointer-events:none;display:block"></canvas>

  {{-- TOAST (flash messages) --}}
  <div id="toast" role="status" aria-live="polite"></div>

  <div class="shell expanded" id="shell">

    {{-- TOPBAR --}}
    <div class="topbar">
      <div class="topbar-left">
        <div class="logo-mark">
          <img src="{{ asset('images/logo.png') }}" alt="Sodano Consulting">
        </div>
        <div class="logo-text">Sodano Consulting</div>
      </div>
      <div class="topbar-center">
        <span class="breadcrumb">
          @isset($breadcrumb)
            @foreach($breadcrumb as $label => $url)
              @if(!$loop->last)
                <a href="{{ $url }}">{{ $label }}</a>
                <span class="sep">/</span>
              @else
                <span class="cur">{{ $label }}</span>
              @endif
            @endforeach
          @else
            <span class="cur">{{ $title ?? 'Dashboard' }}</span>
          @endisset
        </span>
      </div>
      <div class="topbar-right">
        <span class="tb-date">{{ strtoupper(now()->locale('it')->isoFormat('ddd D MMM YYYY')) }}</span>
        <div class="dropdown" x-data="{ open: false }" @click.outside="open = false">
          <button class="tb-btn" @click="open = !open">
            @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
              <div class="badge-notif">{{ $unreadNotificationsCount }}</div>
            @endif
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
          </button>

          <div class="dropdown-menu dropdown-menu-wide" x-show="open" x-transition style="display:none;">
            <div class="dropdown-header">
              <div class="dropdown-header-title">Notifiche</div>
              @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                <div class="u-flex-center u-gap-md">
                  <form method="POST" action="{{ route('notifications.readAll') }}" @click.stop>
                    @csrf
                    <button type="submit" class="notif-mark-all">Segna tutte lette</button>
                  </form>
                  <span class="notif-count-badge">{{ $unreadNotificationsCount }} nuove</span>
                </div>
              @endif
            </div>
            <div class="dropdown-body">
              @forelse($latestNotifications ?? [] as $notification)
                @php /** @var \Illuminate\Notifications\DatabaseNotification $notification */ @endphp
                <div class="notif-item {{ $notification->read_at ? '' : 'unread' }}">
                  <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" class="notif-item-btn">
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
                  </form>
                  <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}"
                    class="notif-item-delete-form" @click.stop>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="notif-item-delete" title="Elimina notifica">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                          d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
                      </svg>
                    </button>
                  </form>
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

        <div class="dropdown" x-data="{ open: false }" @click.outside="open = false">
          <div class="avatar-btn" title="{{ auth()->user()->name }}" @click="open = !open">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name, ' '), 1, 1)) }}
          </div>
          <div class="dropdown-menu" x-show="open" x-transition style="display:none;">
            <div class="dropdown-header stacked">
              <div class="dropdown-header-title">{{ auth()->user()->name }}</div>
              <div class="u-text-meta">{{ auth()->user()->role->value }}</div>
            </div>
            <a href="{{ route('profile.edit') }}" class="dropdown-item">Profilo</a>
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="dropdown-item danger">Esci</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- SIDEBAR --}}
    <div class="sidebar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg id="sidebar-toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6" />
        </svg>
      </button>

      {{-- ACCESSO RAPIDO --}}
      @if(auth()->user()->hasOperationalDashboard() || auth()->user()->canManageSystem())
        <div class="nav-group">
          <div class="nav-group-label">Accesso Rapido</div>
          <div class="sidebar-action-group">
            <a href="{{ route('tasks.create') }}" wire:navigate class="btn btn-p sidebar-action-btn">
              <i data-lucide="plus" class="u-icon-sm"></i>
              <span class="sidebar-btn-text">Nuovo Task</span>
            </a>
            @if(!auth()->user()->isPhotographer() && !auth()->user()->isMarketing())
              @if(isset($newTickets) && $newTickets > 0)
                <a href="{{ route('tickets.index') }}" wire:navigate class="sidebar-ticket-alarm">
                  <i data-lucide="bell-ring" class="u-icon-sm sidebar-alarm-icon"></i>
                  <span class="sidebar-btn-text">
                    {{ $newTickets }} {{ $newTickets === 1 ? 'nuovo ticket' : 'nuovi ticket' }}
                  </span>
                  <span class="sidebar-alarm-badge">{{ $newTickets }}</span>
                </a>
              @endif
            @endif
          </div>
        </div>
      @endif

      {{-- 1. OPERATIVITÀ --}}
      <div class="nav-divider"></div>
      <div class="nav-group">
        <div class="nav-group-label">Operatività</div>

        <x-nav-item href="{{ route('dashboard') }}" icon="layout-dashboard" label="Dashboard"
          :active="request()->routeIs('dashboard')" />

        <x-nav-item href="{{ route('tasks.index') }}" icon="check-square" label="Task"
          :active="request()->routeIs('tasks.*')" :badge="$openTasks ?? null" />

        @if(!auth()->user()->isPhotographer() && !auth()->user()->isMarketing())
          <x-nav-item href="{{ route('tickets.index') }}" icon="ticket" label="Ticket"
            :active="request()->routeIs('tickets.*')" :badge="$openTickets ?? null" />
        @endif

        <x-nav-item href="{{ route('calendar-events.index') }}" icon="calendar" label="Calendario"
          :active="request()->routeIs('calendar-events.*')" />

        @can('viewAny', \App\Models\Team::class)
          @if(!auth()->user()->isPhotographer() && !auth()->user()->isMarketing())
            <x-nav-item href="{{ route('teams.index') }}" icon="users" label="Team"
              :active="request()->routeIs('teams.*')" />
          @endif
        @endcan
      </div>

      {{-- 2. SOCIAL MEDIA — marketing e admin --}}
      @if(auth()->user()->isMarketing() || auth()->user()->canManageSystem())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Social Media</div>

          <x-nav-item href="{{ route('marketing-campaigns.index') }}" icon="megaphone" label="Progetti Marketing"
            :active="request()->routeIs('marketing-campaigns.*')" :badge="$marketingProjectsCount ?? null" />

          <x-nav-item href="{{ route('social.calendar') }}" icon="calendar-days" label="Calendario Campagne"
            :active="request()->routeIs('social.calendar')" />

          @if(auth()->user()->isMarketing())
            <x-nav-item href="{{ route('social.shooting.index') }}" icon="camera" label="Richieste Shooting"
              :active="request()->routeIs('social.shooting.*')" />
          @endif

          @if(auth()->user()->canManageSystem())
            <x-nav-item href="{{ route('admin.shooting.index') }}" icon="camera" label="Gestione Shooting"
              :active="request()->routeIs('admin.shooting.*')" />
          @endif
        </div>
      @endif

      {{-- 2. SOCIAL MEDIA — fotografo --}}
      @if(auth()->user()->isPhotographer())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Social Media</div>
          <x-nav-item href="{{ route('photography.shooting.index') }}" icon="camera" label="I Miei Shooting"
            :active="request()->routeIs('photography.shooting.*')" />
        </div>
      @endif

      {{-- 3. AMMINISTRAZIONE --}}
      <div class="nav-divider"></div>
      <div class="nav-group">
        <div class="nav-group-label">Amministrazione</div>

        @can('viewAny', \App\Models\Client::class)
          @if(!auth()->user()->isPhotographer())
            <x-nav-item href="{{ route('clients.index') }}" icon="building-2" label="Clienti"
              :active="request()->routeIs('clients.*')" :badge="$clientsCount ?? null" />
          @endif
        @endcan

        <x-nav-item href="{{ route('projects.index') }}" icon="folder-kanban" label="Progetti"
          :active="request()->routeIs('projects.*')" :badge="$projectsCount ?? null" />

        @if(auth()->user()->canAccessFinance())
          <x-nav-item href="{{ route('invoices.index') }}" icon="file-text" label="Fatture"
            :active="request()->routeIs('invoices.*')" :badge="$overdueInvoices ?? null" />
          <x-nav-item href="{{ route('payments.index') }}" icon="credit-card" label="Pagamenti"
            :active="request()->routeIs('payments.*')" />
          <x-nav-item href="{{ route('economic-summary.index') }}" icon="bar-chart-2" label="Riepilogo"
            :active="request()->routeIs('economic-summary.*')" />
        @endif
      </div>

      {{-- HOSTING E DOMINI --}}
      @if(auth()->user()->canManageSystem())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Hosting e Domini</div>
          <x-nav-item href="{{ route('hosting-services.index', ['type' => 'domain']) }}" icon="globe" label="Domini"
            :active="request()->routeIs('hosting-services.*') && request('type') === 'domain'" />
          <x-nav-item href="{{ route('hosting-services.index', ['exclude_type' => 'domain']) }}" icon="server"
            label="Hosting" :active="request()->routeIs('hosting-services.*') && request('exclude_type') === 'domain'" />
        </div>
      @endif

      {{-- ADMIN --}}
      @can('system.admin')
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Admin</div>
          <x-nav-item href="{{ route('users.index') }}" icon="user-cog" label="Utenti"
            :active="request()->routeIs('users.*')" />
        </div>
      @endcan

      <div class="nav-spacer"></div>
      <div class="nav-divider"></div>
      <x-nav-item href="{{ route('profile.edit') }}" icon="settings" label="Impostazioni"
        :active="request()->routeIs('profile.*')" />

      <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
        @csrf
        <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" class="nav-item logout">
          <div class="nav-icon"><i data-lucide="log-out" width="16" height="16" stroke-width="1.8"></i></div>
          <span class="nav-label">Logout</span>
          <div class="nav-tooltip">Logout</div>
        </a>
      </form>
    </div>

    {{-- MAIN --}}
    <div class="main">
      <div class="content" id="content-area">
        {{-- Flash messages --}}
        @if(session('success'))
          <div class="flash flash-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
      </div>
    </div>

  </div>

  <script>
    // Flash → toast automatico
    @if(session('success'))
      document.addEventListener('DOMContentLoaded', () => toast("{{ session('success') }}", 'success'));
    @endif
    @if(session('error'))
      document.addEventListener('DOMContentLoaded', () => toast("{{ session('error') }}", 'error'));
    @endif
  </script>

  @stack('scripts')
  @livewireScripts
</body>

</html>