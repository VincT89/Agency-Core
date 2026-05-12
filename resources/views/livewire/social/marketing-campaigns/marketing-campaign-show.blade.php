<div>
  <div class="u-mb-lg u-flex-end">
      <a href="{{ route('marketing-campaigns.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
          <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna ai progetti
      </a>
  </div>

  <x-page-header eyebrow="Progetto Marketing">
    <x-slot:title>
      <div>
        <strong>{{ $campaign->name }}</strong>
        @if($campaign->description)
          <div class="cmp-campaign-desc">{{ $campaign->description }}</div>
        @endif
        <div class="cmp-campaign-meta">
          <span>{{ $campaign->starts_at ? $campaign->starts_at->format('d/m/Y') : 'Da def.' }} - {{ $campaign->ends_at ? $campaign->ends_at->format('d/m/Y') : 'In corso' }}</span>
          <span class="cmp-campaign-meta-sep">•</span>
          <span>{{ $totalPostsCount }} Post</span>
        </div>
      </div>
    </x-slot:title>
    <x-slot name="actions">
      <div class="u-flex-center u-gap-lg">
          <x-badge :status="$campaign->status->value" :label="$campaign->status->label()" />
          <div class="u-flex u-gap-sm">
            @if(auth()->user()->isAdmin())
              <button wire:click="openCampaignModal" class="btn btn-g btn-sm">Modifica</button>
              <button wire:click="openExtendModal" class="btn btn-g btn-sm">Prolunga</button>
              @if(in_array($campaign->status->value, ['closed', 'paused']))
                <button wire:click="openRenewModal" class="btn btn-g btn-sm">Rinnova</button>
              @endif
              <button wire:click="openInvoiceModal" class="btn btn-g btn-sm">Fattura</button>
            @endif
          </div>
      </div>
    </x-slot>
  </x-page-header>

  <div class="cmp-campaign-page">
    
    {{-- Tabella Post --}}
    <x-panel title="Post in Programma" dot="var(--accent)">
        <x-slot:headerActions>
            <a href="{{ route('marketing-campaigns.posts.create', $campaign->id) }}" wire:navigate.hover class="btn btn-p btn-sm u-flex-center u-gap-xs">
                <i data-lucide="plus" class="u-icon-sm"></i> Nuovo Post
            </a>
        </x-slot:headerActions>
        <table class="t-table">
          <thead>
            <tr>
              <th>Data Pub.</th>
              <th>Tipo</th>
              <th>Preview</th>
              <th>Titolo</th>
              <th>Stato</th>
            </tr>
          </thead>
          <tbody>
            @forelse($posts as $post)
              <tr class="relative hover:bg-gray-50 transition-colors group cursor-pointer" @click="Livewire.navigate('{{ route('marketing-campaigns.posts.show', ['campaign' => $campaign->id, 'post' => $post->id]) }}')">
                <td class="font-mono">
                    <a href="{{ route('marketing-campaigns.posts.show', ['campaign' => $campaign->id, 'post' => $post->id]) }}" wire:navigate.hover class="absolute inset-0 z-10" title="Apri post"></a>
                    {{ $post->scheduled_date ? $post->scheduled_date->format('d/m/Y') : 'Da def.' }}
                </td>
                <td><span class="cmp-post-type-badge">{{ $post->content_type->label() }}</span></td>
                <td>
                  @php
                      $vImg = null;
                      if ($post->currentVersion) {
                          if (!empty($post->currentVersion->image_urls) && is_array($post->currentVersion->image_urls)) {
                              $vImg = $post->currentVersion->image_urls[0];
                          } else {
                              $vImg = $post->currentVersion->image_url;
                          }
                      }
                  @endphp
                  @if($vImg)
                        <img src="{{ $vImg }}" class="cmp-post-thumb" loading="lazy" alt="Anteprima media post">
                  @elseif($post->preview_url)
                    @if($post->media_mime && \Illuminate\Support\Str::startsWith($post->media_mime, 'video/'))
                        <video src="{{ $post->preview_url }}" class="cmp-post-thumb" muted playsinline></video>
                    @else
                        <img src="{{ $post->preview_url }}" class="cmp-post-thumb" loading="lazy" alt="Anteprima media post">
                    @endif
                  @else
                    <div class="cmp-post-thumb-empty"><i data-lucide="image" class="w-5 h-5"></i></div>
                  @endif
                </td>
                <td class="font-semibold">{{ $post->currentVersion?->title ?: ($post->title ?: 'Senza Titolo') }}</td>
                <td><x-badge :status="$post->status->value" :label="$post->status->label()" /></td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-gray-500 py-8">Nessun post in programma.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
    </x-panel>

    <div class="cal-gshell" id="mkt-calendar-wrapper">
        <!-- SIDEBAR -->
        <aside class="cal-gsidebar">
            <a href="{{ route('marketing-campaigns.posts.create', $campaign->id) }}" wire:navigate class="btn btn-p u-flex-center u-gap-xs u-w-full u-mb-md">
                <i data-lucide="plus" class="u-icon-sm"></i> Nuovo Post
            </a>

            <!-- Mini-Mese -->
            <div class="cal-mini-month">
                <div class="cal-mini-header">
                    <div class="cal-mini-title">{{ Str::ucfirst($monthName) }}</div>
                    <div class="cal-mini-nav">
                        <a wire:click="goToPreviousCalendarMonth" class="btn-cal-nav u-cursor-pointer"><i
                                data-lucide="chevron-left" class="u-icon-sm"></i></a>
                        <a wire:click="goToNextCalendarMonth" class="btn-cal-nav u-cursor-pointer"><i
                                data-lucide="chevron-right" class="u-icon-sm"></i></a>
                    </div>
                </div>
                
                <div class="cal-mini-grid">
                    <div class="cal-mini-day-name">L</div>
                    <div class="cal-mini-day-name">M</div>
                    <div class="cal-mini-day-name">M</div>
                    <div class="cal-mini-day-name">G</div>
                    <div class="cal-mini-day-name">V</div>
                    <div class="cal-mini-day-name">S</div>
                    <div class="cal-mini-day-name">D</div>
                    
                    @foreach($days as $day)
                        @php
                            $isCurrentMonth = $day->month === $currentDate->month;
                            $isToday = $day->isToday();
                            $isSelected = $day->toDateString() === $currentDate->toDateString();
                            $hasPublication = in_array($day->toDateString(), $publishedDates, true);
                        @endphp
                        <a wire:click="setCalendarDate('{{ $day->toDateString() }}')"
                            data-date="{{ $day->toDateString() }}"
                            class="cal-mini-day u-cursor-pointer {{ $isCurrentMonth ? '' : 'is-other-month' }} {{ $isSelected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }} {{ $hasPublication ? 'has-publication' : '' }}">
                            {{ $day->day }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Stats/Filtri -->
            <div class="cal-sidebar-filters">
                <div class="mkt-filter-group">
                    <label class="form-lbl">Stato Campagna</label>
                    <div class="u-mt-xs">
                        <x-badge :status="$campaign->status->value" :label="$campaign->status->label()" />
                    </div>
                </div>
                <div class="mkt-filter-group u-mt-md">
                    <label class="form-lbl">Totale Post</label>
                    <div class="u-mt-xs">
                        <strong>{{ $totalPostsCount }}</strong> post programmati
                    </div>
                </div>
            </div>
        </aside>

        <!-- CALENDARIO PRINCIPALE -->
        <main class="cal-gmain">
            <div class="cal-wrapper-modern cal-full-height" wire:ignore>
                <div id="marketing-campaign-detail-calendar" class="cal-full-height"></div>
            </div>
        </main>
    </div>

    @if(auth()->user()->isAdmin())
      {{-- Blocchi di Gestione --}}
      <div class="g-2col">
        
        {{-- Storico Periodi --}}
        <x-panel title="Contratto / Periodi">
            @if($campaign->periods->count())
              <table class="t-table">
                <thead>
                  <tr class="cmp-tr">
                    <th class="w-40">Dal - Al</th>
                    <th class="right">Importo</th>
                    <th class="right mkt-pr-0">Stato</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($campaign->periods->sortByDesc('from_date') as $period)
                    <tr class="cmp-tr">
                      <td >
                        {{ $period->from_date->format('d/m/Y') }}<br>
                        <span class="cmp-period-date">{{ $period->to_date ? $period->to_date->format('d/m/Y') : 'In corso' }}</span>
                      </td>
                      <td class="amount">€ {{ number_format($period->amount, 2, ',', '.') }}</td>
                      <td class="right">
                        <x-badge :status="$period->status->value" :label="$period->status->label()" />
                        @if($period->invoice_id)
                      <div class="cmp-inv-factured">Fatturato</div>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <div class="u-text-muted u-text-italic u-p-lg">Nessun periodo registrato.</div>
            @endif
        </x-panel>

        <div class="u-flex-col u-gap-xl">
            {{-- Extra --}}
            <div class="panel u-overflow-hidden">
              <div class="lw-modal-hd">
                <div class="cmp-panel-title">Extra Campagna</div>
                <button wire:click="openExtraModal" class="btn btn-p btn-sm">+ Aggiungi</button>
              </div>
              <div class="u-p-lg">
                @if($campaign->extras->count())
                  <table class="t-table">
                    <tbody>
                      @foreach($campaign->extras->sortByDesc('created_at') as $extra)
                        <tr class="cmp-tr">
                          <td class="w-50">
                            <div>{{ $extra->description }}</div>
                            <div class="u-text-meta">{{ $extra->occurred_on ? $extra->occurred_on->format('d/m/Y') : '-' }}</div>
                          </td>
                          <td class="amount">€ {{ number_format($extra->amount, 2, ',', '.') }}</td>
                          <td class="u-flex-end u-gap-sm">
                            <x-badge :status="$extra->status->value" :label="$extra->status->label()" />
                            @if(!$extra->invoice_id)
                              <x-delete-modal wireClick="deleteExtra({{ $extra->id }})" title="Annulla Extra" message="Sei sicuro di voler annullare questo extra?">
                                <button type="button" class="btn-ghost-danger btn-xs u-text-muted">
                                  <i data-lucide="trash-2" class="u-icon-sm"></i>
                                </button>
                              </x-delete-modal>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @else
                  <div class="u-text-muted u-text-italic">Nessun extra registrato.</div>
                @endif
              </div>
            </div>

        </div>
      </div>
      
      {{-- Storico Fatture --}}
      <div class="panel u-overflow-hidden">
        <div class="lw-modal-hd">
          <div class="cmp-panel-title">Storico Fatture</div>
          <button wire:click="openInvoiceModal" class="btn btn-p btn-sm">Genera Fattura</button>
        </div>
        <div class="u-p-lg">
          @if($campaign->invoices && $campaign->invoices->count())
            <table class="t-table">
              <tbody>
                @foreach($campaign->invoices->sortByDesc('issue_date') as $invoice)
                  <tr class="cmp-tr">
                    <td >
                      <div class="mkt-fw-bold">Fattura #{{ $invoice->number }}</div>
                      <div class="u-text-meta">Emissione: {{ $invoice->issue_date->format('d/m/Y') }}</div>
                    </td>
                    <td class="amount">
                      € {{ number_format($invoice->tax_amount, 2, ',', '.') }}<br>
                      <span class="mkt-text-xs-meta">Scadenza: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="u-text-muted u-text-italic">Nessuna fattura registrata.</div>
          @endif
        </div>
      </div>
    @endif

    @if($campaign->notes)
      <x-panel title="Note Interne" padded>
        <div class="t-body">{{ $campaign->notes }}</div>
      </x-panel>
    @endif

  </div>

  {{-- Modale: Modifica Campagna --}}
  @if($showCampaignModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Modifica Campagna</h3>
          <button type="button" wire:click="closeCampaignModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <div class="form-g">
            <label class="form-lbl">Nome Campagna</label>
            <input type="text" class="form-in" wire:model="campaignForm.name">
            @error('campaignForm.name') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <textarea class="form-ta" wire:model="campaignForm.description"></textarea>
          </div>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Inizio</label>
                <input type="date" class="form-in" wire:model="campaignForm.starts_at">
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Fine</label>
                <input type="date" class="form-in" wire:model="campaignForm.ends_at">
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Stato</label>
            <select class="form-sel" wire:model="campaignForm.status">
              <option value="draft">Bozza</option>
              <option value="active">Attiva</option>
              <option value="paused">In Pausa</option>
              <option value="closed">Chiusa</option>
            </select>
          </div>
          <div class="form-g">
            <label class="form-lbl">Budget Mensile (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="campaignForm.monthly_fee">
          </div>
          <div class="form-g">
            <label class="form-lbl">Note Interne</label>
            <textarea class="form-ta" wire:model="campaignForm.notes"></textarea>
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeCampaignModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="saveCampaign" class="btn btn-p">Salva Modifiche</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Prolunga Campagna --}}
  @if($showExtendModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Prolunga Campagna</h3>
          <button type="button" wire:click="closeExtendModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <p class="u-text-muted u-mb-md">
            Aggiungi un nuovo periodo di fatturazione. Lo stato della campagna tornerà "Attivo" se era chiuso o in pausa.
          </p>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Dal</label>
                <input type="date" class="form-in" wire:model="extendForm.from_date">
                @error('extendForm.from_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Al (opzionale)</label>
                <input type="date" class="form-in" wire:model="extendForm.to_date">
                @error('extendForm.to_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="extendForm.amount">
            @error('extendForm.amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <input type="text" class="form-in" wire:model="extendForm.description">
            @error('extendForm.description') <span class="form-err">{{ $message }}</span> @enderror
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeExtendModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="extendCampaign" class="btn btn-p">Prolunga</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Rinnova Campagna --}}
  @if($showRenewModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Rinnova Campagna</h3>
          <button type="button" wire:click="closeRenewModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          <p class="u-text-muted u-mb-md">
            Riattiva questa campagna chiusa o in pausa creando un nuovo contratto/periodo.
          </p>
          <div class="form-g">
            <label class="form-lbl">Nuova Data Inizio Contratto (starts_at)</label>
            <input type="date" class="form-in" wire:model="renewForm.starts_at">
          </div>
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Primo Periodo Dal</label>
                <input type="date" class="form-in" wire:model="renewForm.from_date">
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Al (opzionale)</label>
                <input type="date" class="form-in" wire:model="renewForm.to_date">
            </div>
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="renewForm.amount">
          </div>
          <div class="form-g">
            <label class="form-lbl">Descrizione</label>
            <input type="text" class="form-in" wire:model="renewForm.description">
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeRenewModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="renewCampaign" class="btn btn-p">Rinnova</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Aggiungi Extra --}}
  @if($showExtraModal)
    <div class="lw-overlay">
      <div class="lw-modal lw-modal-sm">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Aggiungi Extra</h3>
          <button type="button" wire:click="closeExtraModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body">
          <div class="form-g">
            <label class="form-lbl">Descrizione Servizio</label>
            <input type="text" class="form-in" wire:model="extraForm.description" placeholder="Es: Shooting fotografico">
            @error('extraForm.description') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Importo (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="extraForm.amount">
            @error('extraForm.amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          <div class="form-g">
            <label class="form-lbl">Data di Svolgimento</label>
            <input type="date" class="form-in" wire:model="extraForm.occurred_on">
            @error('extraForm.occurred_on') <span class="form-err">{{ $message }}</span> @enderror
          </div>
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeExtraModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="addExtra" class="btn btn-p">Salva Extra</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modale: Genera Fattura --}}
  @if($showInvoiceModal)
    <div class="lw-overlay">
      <div class="lw-modal">
        <div class="lw-modal-hd">
          <h3 class="lw-modal-hd-title">Genera Fattura</h3>
          <button type="button" wire:click="closeInvoiceModal" class="btn-ghost-white">
            <i data-lucide="x" class="u-icon-lg"></i>
          </button>
        </div>
        <div class="lw-modal-body custom-scrollbar">
          @if(count($pendingPeriodsForInvoice) > 0)
          <div class="cmp-client-box">
            <div class="cmp-section-label light">Periodi da fatturare</div>
            @foreach($pendingPeriodsForInvoice as $period)
            <label class="cmp-check-label muted">
              <input type="checkbox" wire:model="invoiceForm.period_ids" value="{{ $period['id'] }}" class="u-accent-blue">
              <span>{{ $period['description'] }} - <strong class="mkt-text-primary">€ {{ number_format($period['amount'], 2, ',', '.') }}</strong></span>
            </label>
            @endforeach
          </div>
          @endif

          @if(count($pendingExtrasForInvoice) > 0)
          <div class="cmp-client-box">
            <div class="cmp-section-label light">Extra da fatturare</div>
            @foreach($pendingExtrasForInvoice as $extra)
            <label class="cmp-check-label muted">
              <input type="checkbox" wire:model="invoiceForm.extra_ids" value="{{ $extra['id'] }}" class="u-accent-blue">
              <span>{{ $extra['description'] }} - <strong class="mkt-text-primary">€ {{ number_format($extra['amount'], 2, ',', '.') }}</strong></span>
            </label>
            @endforeach
          </div>
          @endif
          
          {{-- Voci Aggiuntive --}}
          <div class="inv-custom-lines-section">
              <div class="inv-custom-lines-hd">
                  <div class="cmp-section-label">Voci aggiuntive</div>
                  <button type="button" wire:click="addCustomLine" class="btn btn-s btn-xs">
                      <i data-lucide="plus" class="u-icon-sm"></i> Aggiungi voce
                  </button>
              </div>
              @foreach($customLines as $i => $line)
                  <div class="inv-custom-line-row" wire:key="line-{{ $i }}">
                      <input type="text"
                             wire:model="customLines.{{ $i }}.description"
                             class="form-in inv-line-desc"
                             placeholder="Descrizione (es. Lavori extra, Consulenza...)">
                      <input type="number"
                             wire:model="customLines.{{ $i }}.quantity"
                             class="form-in inv-line-qty"
                             placeholder="Qtà" min="0.01" step="0.01">
                      <input type="number"
                             wire:model="customLines.{{ $i }}.unit_price"
                             class="form-in inv-line-price"
                             placeholder="€ Prezzo" min="0" step="0.01">
                      <button type="button" wire:click="removeCustomLine({{ $i }})" class="btn-ghost-danger">
                          <i data-lucide="trash-2" class="u-icon-sm"></i>
                      </button>
                  </div>
                  @error("customLines.{$i}.description") <span class="form-err">{{ $message }}</span> @enderror
                  @error("customLines.{$i}.unit_price") <span class="form-err">{{ $message }}</span> @enderror
              @endforeach
          </div>

          @error('invoiceForm') <div class="form-err mkt-form-err-bold">{{ $message }}</div> @enderror
          
          <div class="u-flex u-gap-lg">
              <div class="form-g u-flex-1">
                <label class="form-lbl">Numero Fattura</label>
                <input type="text" class="form-in" wire:model="invoiceForm.number" placeholder="Es: FAT-001/26">
                @error('invoiceForm.number') <span class="form-err">{{ $message }}</span> @enderror
              </div>
          </div>
          
          <div class="u-flex u-gap-lg">
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Emissione</label>
                <input type="date" class="form-in" wire:model="invoiceForm.issue_date">
                @error('invoiceForm.issue_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
            <div class="form-g u-flex-1">
                <label class="form-lbl">Data Scadenza</label>
                <input type="date" class="form-in" wire:model="invoiceForm.due_date">
                @error('invoiceForm.due_date') <span class="form-err">{{ $message }}</span> @enderror
            </div>
          </div>
          
          <div class="form-g">
            <label class="form-lbl">Ammontare Tasse/IVA (€)</label>
            <input type="number" step="0.01" class="form-in" wire:model="invoiceForm.tax_amount">
            @error('invoiceForm.tax_amount') <span class="form-err">{{ $message }}</span> @enderror
          </div>
          
        </div>
        <div class="lw-modal-ft">
          <button type="button" wire:click="closeInvoiceModal" class="btn btn-s">Annulla</button>
          <button type="button" wire:click="generateInvoice" class="btn btn-p btn-green">Genera Fattura</button>
        </div>
      </div>
    </div>
  @endif

</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/it.global.min.js"></script>
<script>
    function cleanupMarketingCampaignDetailCalendar() {
        if (window.marketingCampaignDetailCalendar) {
            window.marketingCampaignDetailCalendar.destroy();
            window.marketingCampaignDetailCalendar = null;
        }
        if (window.marketingCampaignDetailUnsubscribers) {
            window.marketingCampaignDetailUnsubscribers.forEach(unsub => {
                if (typeof unsub === 'function') unsub();
            });
        }
        window.marketingCampaignDetailUnsubscribers = [];
    }

    function initMarketingCampaignDetailCalendar(component) {
        cleanupMarketingCampaignDetailCalendar();

        var calendarEl = document.getElementById('marketing-campaign-detail-calendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        window.marketingCampaignDetailCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            initialDate: '{{ $calendarDate }}',
            locale: 'it',
            firstDay: 1,
            headerToolbar: {
                left: 'today prev,next',
                center: 'title',
                right: 'timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Oggi',
                month: 'Mese',
                week: 'Settimana',
                day: 'Giorno'
            },
            themeSystem: 'standard',
            height: '100%',
            expandRows: true,
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            slotDuration: '01:00:00',
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            allDaySlot: false,
            defaultTimedEventDuration: '01:00:00',
            dayHeaderFormat: { weekday: 'short', day: '2-digit', omitCommas: true },
            slotLabelFormat: { hour: '2-digit', minute: '2-digit', omitZeroMinute: false, meridiem: false },
            
            selectable: true,
            selectMirror: true,
            select: function(info) {
                const start = encodeURIComponent(info.startStr);
                window.Livewire.navigate("{{ route('marketing-campaigns.posts.create', $campaign->id) }}" + "?date=" + start);
            },

            events: function(fetchInfo, successCallback, failureCallback) {
                component.fetchEvents().then(events => successCallback(events)).catch(err => failureCallback(err));
            },
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) window.Livewire.navigate(info.event.url);
            },
            eventContent: function(arg) {
                let wrapper = document.createElement('div');
                wrapper.classList.add('cal-mkt-event');
                
                let titleEl = document.createElement('div');
                titleEl.classList.add('cal-mkt-event-title');
                titleEl.textContent = arg.event.title;
                
                let subEl = document.createElement('div');
                subEl.classList.add('cal-mkt-event-sub');
                subEl.textContent = arg.event.extendedProps.platform + ' - ' + arg.event.extendedProps.status;
                
                wrapper.appendChild(titleEl);
                wrapper.appendChild(subEl);
                
                return { domNodes: [ wrapper ] };
            }
        });

        window.marketingCampaignDetailCalendar.render();

        window.marketingCampaignDetailUnsubscribers.push(
            Livewire.on('marketing-campaign-detail-calendar-date-changed', (payload) => {
                if (!window.marketingCampaignDetailCalendar) return;

                let date = Array.isArray(payload) ? payload[0].date : payload.date;

                const dayEl = document.querySelector(`.cal-mini-day[data-date="${date}"]`);

                if (dayEl && dayEl.classList.contains('has-publication')) {
                    window.marketingCampaignDetailCalendar.changeView('timeGridDay', date);
                } else {
                    window.marketingCampaignDetailCalendar.gotoDate(date);
                }
            })
        );

        const refetchDetailEvents = () => window.marketingCampaignDetailCalendar?.refetchEvents();
        
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-updated', refetchDetailEvents));
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-extended', refetchDetailEvents));
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-renewed', refetchDetailEvents));
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-extra-added', refetchDetailEvents));
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-extra-deleted', refetchDetailEvents));
        window.marketingCampaignDetailUnsubscribers.push(Livewire.on('campaign-invoice-generated', refetchDetailEvents));
    }

    document.addEventListener('livewire:navigating', cleanupMarketingCampaignDetailCalendar);

    document.addEventListener('livewire:navigated', function() {
        const calendarEl = document.getElementById('marketing-campaign-detail-calendar');
        if (!calendarEl) return;
        try {
            initMarketingCampaignDetailCalendar(@this);
        } catch(e) { console.error(e); }
    });
</script>
@endpush
