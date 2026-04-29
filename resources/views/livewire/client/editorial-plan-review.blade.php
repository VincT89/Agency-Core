<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div style="background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.08); overflow:hidden;">
        
        {{-- HEADER --}}
        <div style="background:var(--purple); padding:30px; color:white; text-align:center;">
            <h1 class="page-title" style="margin:0; font-size:32px; color:white;">Revisione Piano Editoriale</h1>
            <p style="margin-top:10px; opacity:0.9;">{{ $plan->marketingProject->title }}</p>
        </div>

        @if (session()->has('success'))
            <div style="padding:40px; text-align:center;">
                <div style="background:var(--green)20; color:var(--green); border-radius:50%; width:80px; height:80px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <i data-lucide="check" style="width:40px; height:40px;"></i>
                </div>
                <h2 style="color:var(--text); margin-bottom:10px;">Operazione Completata</h2>
                <p style="color:var(--text2); font-size:16px;">{{ session('success') }}</p>
            </div>
        @else
            <div style="padding:30px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid var(--border);">
                    <div>
                        <span style="color:var(--text3); font-size:13px; text-transform:uppercase; letter-spacing:1px;">Periodo</span>
                        <div style="font-size:16px; font-weight:600;">
                            {{ $plan->start_date?->format('d/m/Y') }} - {{ $plan->end_date?->format('d/m/Y') }}
                        </div>
                    </div>
                    <div>
                        <span style="color:var(--text3); font-size:13px; text-transform:uppercase; letter-spacing:1px;">Stato Attuale</span>
                        <div style="font-size:16px; font-weight:600; color:var(--orange);">In attesa di revisione</div>
                    </div>
                </div>

                <div style="margin-bottom:40px;">
                    <h3 style="font-size:18px; margin-bottom:20px;">Post Previsti</h3>
                    
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        @foreach($plan->slots as $slot)
                            <div style="border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                                <div style="background:var(--bg2); padding:12px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:var(--text);">{{ $slot->scheduled_date?->format('l, d F Y') }} - {{ \Carbon\Carbon::parse($slot->scheduled_time)->format('H:i') }}</strong>
                                    <div style="display:flex; gap:5px;">
                                        @foreach($slot->platforms ?? [] as $plat)
                                            <span style="background:var(--bg3); padding:4px 8px; border-radius:4px; font-size:11px; text-transform:uppercase;">{{ $plat }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div style="padding:20px; display:flex; gap:20px;">
                                    @if($slot->socialPost && $slot->socialPost->currentVersion)
                                        @if($slot->socialPost->currentVersion->image_path)
                                            <div style="width:200px; flex-shrink:0;">
                                                <img src="{{ Storage::url($slot->socialPost->currentVersion->image_path) }}" style="width:100%; border-radius:8px; object-fit:cover;">
                                            </div>
                                        @endif
                                        <div style="flex:1;">
                                            <div style="white-space:pre-wrap; font-size:14px; color:var(--text2); line-height:1.6;">{{ $slot->socialPost->currentVersion->caption ?? 'Nessuna caption fornita.' }}</div>
                                        </div>
                                    @else
                                        <div style="color:var(--text3); font-style:italic;">Contenuto in fase di elaborazione...</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- AZIONI CLIENTE --}}
                @if($isExpired)
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); color: var(--red); padding: 20px; border-radius: 12px; text-align: center; font-weight: 600; font-size: 14px; margin-top: 30px;">
                        Questo link è scaduto. Contatta il team marketing.
                    </div>
                @else
                    <div style="background:var(--bg2); padding:30px; border-radius:12px;">
                        <h3 style="font-size:18px; margin-bottom:15px; text-align:center;">Cosa ne pensi?</h3>
                        
                        <div style="display:flex; gap:15px; justify-content:center; margin-bottom:20px;">
                            <button wire:click="approve" class="btn" style="background:var(--green); color:white; border:none; padding:12px 24px; font-size:16px;">
                                <i data-lucide="check-circle" style="margin-right:8px; width:20px; height:20px; vertical-align:middle;"></i>
                                Approva Piano
                            </button>
                        </div>

                        <div style="text-align:center; margin-top:30px; padding-top:20px; border-top:1px dashed var(--border);">
                            <p style="color:var(--text3); font-size:14px; margin-bottom:15px;">Se c'è qualcosa che non va, puoi richiedere delle modifiche:</p>
                            <form wire:submit="requestChanges" style="max-width:500px; margin:0 auto; text-align:left;">
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:13px; margin-bottom:5px; color:var(--text2);">Il tuo Nome *</label>
                                    <input type="text" wire:model="clientName" class="t-input" required placeholder="Mario Rossi">
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:13px; margin-bottom:5px; color:var(--text2);">Richiesta di Modifica *</label>
                                    <textarea wire:model="comment" class="t-input" rows="4" required placeholder="Es: Il post del 15 aprile ha un'immagine troppo scura..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-secondary" style="width:100%;">Invia Richiesta di Modifica</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>
