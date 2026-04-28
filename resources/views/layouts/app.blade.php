<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Sodano Consulting' }}</title>
  <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@300;400;500&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

{{-- Background Canvas --}}
<canvas id="bg-canvas" aria-hidden="true"
        style="position:fixed;inset:0;z-index:0;pointer-events:none;display:block"></canvas>

{{-- TOAST (flash messages) --}}
<div id="toast"></div>

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
              <a href="{{ $url }}" style="color:var(--text2);text-decoration:none">{{ $label }}</a>
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
      <div style="position:relative" id="notif-wrap">
          <button class="tb-btn" id="notif-btn" style="position:relative" onclick="event.stopPropagation(); const m = document.getElementById('notif-menu'); m.style.display = m.style.display === 'none' ? 'block' : 'none';">
            @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
              <div class="badge-notif">{{ $unreadNotificationsCount }}</div>
            @endif
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
          </button>
          
          <div id="notif-menu" style="
              display:none; position:absolute; top:38px; right:0;
              background:var(--bg2); border:1px solid var(--line2);
              border-radius:var(--r); min-width:280px; z-index:300;
              box-shadow:0 4px 16px rgba(0,0,0,.4);
          ">
              <div style="padding:10px 14px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                  <div style="font-size:12px;font-weight:600;color:var(--text)">Notifiche</div>
                  @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                    <div style="display:flex;align-items:center;gap:12px">
                        <form method="POST" action="{{ route('notifications.readAll') }}" style="margin:0" onsubmit="event.stopPropagation();">
                            @csrf
                            <button type="submit" style="background:transparent;border:none;cursor:pointer;color:var(--text2);font-size:10px;text-decoration:underline;white-space:nowrap" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                                Segna tutte lette
                            </button>
                        </form>
                        <span style="font-size:10px;background:var(--red);color:#fff;padding:2px 6px;border-radius:10px;white-space:nowrap">{{ $unreadNotificationsCount }} nuove</span>
                    </div>
                  @endif
              </div>
              <div style="max-height:300px;overflow-y:auto">
                  @forelse($latestNotifications ?? [] as $notification)
                      @php /** @var \Illuminate\Notifications\DatabaseNotification $notification */ @endphp
                      <div style="position:relative;border-bottom:1px solid var(--line);background: {{ $notification->read_at ? 'transparent' : 'var(--bg3)' }};" 
                           onmouseover="this.style.background='var(--bg)'" 
                           onmouseout="this.style.background='{{ $notification->read_at ? 'transparent' : 'var(--bg3)' }}'">
                          <form method="POST" action="{{ route('notifications.read', $notification->id) }}" style="margin:0">
                              @csrf
                              <button type="submit" style="
                                  width:100%;text-align:left;padding:12px 14px; padding-right: 40px;
                                  background: transparent;
                                  border:none; cursor:pointer; display:block;
                              ">
                                  @php
                                      $iconName = 'bell';
                                      $nType = $notification->data['type'] ?? '';
                                      if (in_array($nType, ['task_assigned', 'task_due_soon'])) $iconName = 'check-square';
                                      elseif ($nType === 'ticket_assigned') $iconName = 'ticket';
                                      elseif (in_array($nType, ['invoice_overdue', 'payment_recorded'])) $iconName = 'credit-card';
                                  @endphp
                                  <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                      <i data-lucide="{{ $iconName }}" width="14" height="14" stroke-width="2" style="color:var(--text2)"></i>
                                      <span>{{ $notification->data['title'] ?? 'Nuova Notifica' }}</span>
                                  </div>
                                  <div style="font-size:11px;color:var(--text2);line-height:1.4">{{ $notification->data['message'] ?? '' }}</div>
                                  <div style="font-size:10px;color:var(--text3);margin-top:6px;font-family:var(--mono)">{{ $notification->created_at->diffForHumans() }}</div>
                              </button>
                          </form>
                          <!-- Clear Button -->
                          <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" style="position:absolute;top:10px;right:10px;margin:0" onsubmit="event.stopPropagation();">
                              @csrf
                              @method('DELETE')
                              <button type="submit" style="background:transparent;border:none;cursor:pointer;color:var(--text3);padding:4px;display:flex;align-items:center;justify-content:center" 
                                      onmouseover="this.style.color='var(--red)'" 
                                      onmouseout="this.style.color='var(--text3)'"
                                      title="Elimina notifica">
                                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg>
                              </button>
                          </form>
                      </div>
                  @empty
                      <div style="padding:40px 20px;text-align:center;color:var(--text3);display:flex;flex-direction:column;align-items:center;gap:12px">
                          <i data-lucide="inbox" style="width:32px;height:32px;opacity:0.5"></i>
                          <div style="font-size:13px;font-weight:500;color:var(--text2)">Tutto tranquillo!</div>
                          <div style="font-size:11px">Non hai nuove notifiche da leggere.</div>
                      </div>
                  @endforelse
              </div>
          </div>
      </div>
      <div style="position:relative" id="avatar-wrap">
          <div class="avatar-btn"
               title="{{ auth()->user()->name }}"
               onclick="document.getElementById('user-menu').classList.toggle('open')"
               style="cursor:pointer">
              {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name, ' '), 1, 1)) }}
          </div>
          <div id="user-menu" style="
              display:none; position:absolute; top:38px; right:0;
              background:var(--bg2); border:1px solid var(--line2);
              border-radius:var(--r); min-width:160px; z-index:300;
              box-shadow:0 4px 16px rgba(0,0,0,.4);
          ">
              <div style="padding:10px 14px;border-bottom:1px solid var(--line)">
                  <div style="font-size:12px;font-weight:600;color:var(--text)">{{ auth()->user()->name }}</div>
                  <div style="font-family:var(--mono);font-size:10px;color:var(--text3)">{{ auth()->user()->role->value }}</div>
              </div>
              <a href="{{ route('profile.edit') }}"
                 style="display:block;padding:9px 14px;font-size:12px;color:var(--text2);text-decoration:none;transition:background .12s"
                 onmouseover="this.style.background='var(--bg3)'"
                 onmouseout="this.style.background=''">
                  Profilo
              </a>
              <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button type="submit" style="
                      width:100%;text-align:left;padding:9px 14px;
                      font-size:12px;font-family:var(--sans);
                      color:var(--red);background:transparent;
                      border:none;cursor:pointer;transition:background .12s;
                  "
                  onmouseover="this.style.background='rgba(245,75,75,.08)'"
                  onmouseout="this.style.background=''">
                      Esci
                  </button>
              </form>
          </div>
      </div>
    </div>
  </div>

  {{-- SIDEBAR --}}
  <div class="sidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <svg id="sidebar-toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m15 18-6-6 6-6"/>
      </svg>
    </button>

    <div class="nav-group">
      <div class="nav-group-label">Principale</div>
      <x-nav-item href="{{ route('dashboard') }}" icon="layout-dashboard" label="Dashboard" :active="request()->routeIs('dashboard')" />
      @can('viewAny', \App\Models\Client::class)
        @if(!auth()->user()->isPhotographer())
          <x-nav-item href="{{ route('clients.index') }}" icon="building-2" label="Clienti" :active="request()->routeIs('clients.*')" :badge="$clientsCount ?? null" />
        @endif
      @endcan
      <x-nav-item href="{{ route('projects.index') }}" icon="folder-kanban" label="Progetti" :active="request()->routeIs('projects.*')" :badge="$projectsCount ?? null" />
    </div>

    @can('viewAny', \App\Models\Shooting\Shoot::class)
    
      @if(!auth()->user()->isPhotographer() && !auth()->user()->canManageSystem())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Social</div>
          <x-nav-item href="{{ route('social.shooting.index') }}" icon="camera" label="Richieste Shooting" :active="request()->routeIs('social.shooting.*')" />
          <x-nav-item href="{{ route('marketing-projects.index') }}" icon="megaphone" label="Progetti Marketing" :active="request()->routeIs('marketing-projects.*')" />
          <x-nav-item href="{{ route('social.posts.index') }}" icon="instagram" label="Post" :active="request()->routeIs('social.posts.*')" />
          <x-nav-item href="{{ route('social.calendar') }}" icon="calendar-days" label="Piano Editoriale" :active="request()->routeIs('social.calendar')" />
        </div>
      @endif

      @if(auth()->user()->isPhotographer())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Shooting</div>
          <x-nav-item href="{{ route('photography.shooting.index') }}" icon="camera" label="I Miei Shooting" :active="request()->routeIs('photography.shooting.*')" />
        </div>
      @endif

      @if(auth()->user()->canManageSystem())
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Shooting</div>
          <x-nav-item href="{{ route('admin.shooting.index') }}" icon="camera" label="Gestione Shooting" :active="request()->routeIs('admin.shooting.*')" />
        </div>
        
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Social</div>
          <x-nav-item href="{{ route('marketing-projects.index') }}" icon="megaphone" label="Progetti Marketing" :active="request()->routeIs('marketing-projects.*') && !request()->routeIs('marketing-projects.publication-board')" />
          <x-nav-item href="{{ route('marketing-projects.publication-board') }}" icon="inbox" label="Da Pubblicare" :active="request()->routeIs('marketing-projects.publication-board')" />
          <x-nav-item href="{{ route('social.posts.index') }}" icon="instagram" label="Gestione Social Post" :active="request()->routeIs('social.posts.*')" />
          <x-nav-item href="{{ route('social.calendar') }}" icon="calendar-days" label="Piano Editoriale" :active="request()->routeIs('social.calendar')" />
        </div>
      @endif
      
    @endcan

    @if(auth()->user()->hasOperationalDashboard() || auth()->user()->canManageSystem())
    <div class="nav-divider"></div>

    <div class="nav-group">
      <div class="nav-group-label">Operatività</div>
      @can('viewAny', \App\Models\Team::class)
        @if(!auth()->user()->isPhotographer() && !auth()->user()->isMarketing())
          <x-nav-item href="{{ route('teams.index') }}" icon="users" label="Team" :active="request()->routeIs('teams.*')" />
        @endif
      @endcan
      
      <x-nav-item href="{{ route('tasks.index') }}" icon="check-square" label="Task" :active="request()->routeIs('tasks.*')" />
      
      <x-nav-item href="{{ route('calendar-events.index') }}" icon="calendar" label="Calendario" :active="request()->routeIs('calendar-events.*')" />
      
      @if(!auth()->user()->isPhotographer() && !auth()->user()->isMarketing())
      <x-nav-item href="{{ route('tickets.index') }}" icon="ticket" label="Ticket" :active="request()->routeIs('tickets.*')" :badge="$openTickets ?? null" badge-class="b-warn" />
      @endif
    </div>
    @endif



    @if(auth()->user()->canAccessFinance())
    <div class="nav-divider"></div>

    <div class="nav-group">
      <div class="nav-group-label">Amministrazione</div>
      <x-nav-item href="{{ route('invoices.index') }}" icon="file-text" label="Fatture" :active="request()->routeIs('invoices.*')" :badge="$overdueInvoices ?? null" badge-class="b-warn" />
      <x-nav-item href="{{ route('payments.index') }}" icon="credit-card" label="Pagamenti" :active="request()->routeIs('payments.*')" />
      <x-nav-item href="{{ route('economic-summary.index') }}" icon="bar-chart-2" label="Riepilogo" :active="request()->routeIs('economic-summary.*')" />
    </div>
    @endif

    @can('system.admin')
      <div class="nav-divider"></div>

      <div class="nav-group">
        <div class="nav-group-label">Admin</div>
        <x-nav-item
          href="{{ route('users.index') }}"
          icon="user-cog"
          label="Utenti"
          :active="request()->routeIs('users.*')"
        />
      </div>
    @endcan

    <div class="nav-spacer"></div>
    <div class="nav-divider"></div>
    <x-nav-item href="{{ route('profile.edit') }}" icon="settings" label="Impostazioni" :active="request()->routeIs('profile.*')" />
    
    <form method="POST" action="{{ route('logout') }}" style="width: 100%;">
      @csrf
      <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" class="nav-item" style="text-decoration:none; color: var(--red);">
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
let sidebarOpen = true;
function toggleSidebar() {
  sidebarOpen = !sidebarOpen;
  document.getElementById('shell').classList.toggle('expanded', sidebarOpen);
  const icon = document.getElementById('sidebar-toggle-icon');
  if (icon) {
      icon.innerHTML = sidebarOpen 
          ? '<path d="m15 18-6-6 6-6"/>' 
          : '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>';
  }
}

// Flash → toast automatico
@if(session('success'))
  document.addEventListener('DOMContentLoaded', () => toast('{{ session('success') }} ✓'));
@endif

function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2600);
}

// Chiudi modal con Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open'));
});
document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// Chiudi user menu e notif menu cliccando fuori
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('avatar-wrap');
    const menu = document.getElementById('user-menu');
    if (wrap && !wrap.contains(e.target)) {
        if(menu) {
            menu.style.display = 'none';
            menu.classList.remove('open');
        }
    }
    
    const notifWrap = document.getElementById('notif-wrap');
    const notifMenu = document.getElementById('notif-menu');
    if (notifWrap && !notifWrap.contains(e.target)) {
        if(notifMenu) {
            notifMenu.style.display = 'none';
        }
    }
});
document.getElementById('user-menu')?.addEventListener('click', function(e) {
    e.stopPropagation();
});
document.getElementById('notif-menu')?.addEventListener('click', function(e) {
    e.stopPropagation();
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

// Toggle open/close avatar menu
const origToggle = HTMLElement.prototype.classList.toggle;
document.querySelector('.avatar-btn')?.addEventListener('click', function() {
    const menu = document.getElementById('user-menu');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
});


</script>

@stack('scripts')
</body>
</html>
