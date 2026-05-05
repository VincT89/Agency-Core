<div class="min-h-screen bg-[#111111] py-12 px-4 sm:px-6 lg:px-8 text-gray-200 font-sans">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-light text-white mb-2 tracking-tight">Revisione Post</h1>
            <p class="text-gray-400">Campagna: <span class="font-semibold text-[#f1c40f]">{{ $post->campaign->name }}</span></p>
        </div>

        @if(session('success'))
            <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-6 mb-8 text-center backdrop-blur-sm shadow-xl">
                <svg class="mx-auto h-12 w-12 text-green-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-medium text-white mb-2">{{ session('success') }}</h3>
                <p class="text-green-300/80">Puoi chiudere questa finestra.</p>
            </div>
        @else
            <div class="bg-[#1a1a1a] rounded-2xl shadow-2xl overflow-hidden border border-gray-800 mb-8 transition-all hover:border-gray-700">
                <div class="p-8">
                    @if($post->currentVersion?->image_url)
                        <div class="mb-8 rounded-xl overflow-hidden bg-black/50 aspect-video flex items-center justify-center ring-1 ring-white/10 shadow-inner">
                            <img src="{{ $post->currentVersion->image_url }}" alt="Anteprima Post" class="max-h-full max-w-full object-contain">
                        </div>
                    @endif

                    <div class="space-y-6">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Titolo</h2>
                            <p class="text-xl text-white font-medium leading-relaxed">{{ $post->currentVersion?->title ?: 'N/A' }}</p>
                        </div>
                        
                        <div class="h-px bg-gray-800"></div>

                        <div>
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Testo del Post</h2>
                            <div class="prose prose-invert max-w-none text-gray-300 whitespace-pre-wrap leading-relaxed">
                                {{ $post->currentVersion?->caption ?: 'N/A' }}
                            </div>
                        </div>

                        @if($post->currentVersion?->hashtags)
                            <div class="h-px bg-gray-800"></div>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Hashtags</h2>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($post->currentVersion->hashtags as $hashtag)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#f1c40f]/10 text-[#f1c40f] border border-[#f1c40f]/20 shadow-sm">
                                            #{{ str_replace('#', '', $hashtag) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-[#1a1a1a] rounded-2xl shadow-2xl p-8 border border-gray-800">
                <h3 class="text-xl font-light text-white mb-6">Il tuo Feedback</h3>

                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Nome</label>
                            <input type="text" wire:model="clientName" class="w-full bg-[#111] border-gray-700 rounded-lg shadow-sm text-white focus:ring-[#f1c40f] focus:border-[#f1c40f] transition-colors" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                            <input type="email" wire:model="clientEmail" class="w-full bg-[#111] border-gray-700 rounded-lg shadow-sm text-white focus:ring-[#f1c40f] focus:border-[#f1c40f] transition-colors" readonly>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Richiesta di Modifiche (opzionale se approvi)</label>
                        <textarea wire:model="commentBody" rows="4" class="w-full bg-[#111] border-gray-700 rounded-lg shadow-sm text-white focus:ring-[#f1c40f] focus:border-[#f1c40f] transition-colors resize-none placeholder-gray-600" placeholder="Scrivi qui eventuali correzioni o modifiche richieste..."></textarea>
                        @error('commentBody') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex flex-col sm:flex-row gap-4 items-center justify-end border-t border-gray-800 mt-6">
                        <button wire:click="requestChanges" wire:loading.attr="disabled" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-gray-600 shadow-sm text-sm font-medium rounded-xl text-gray-300 bg-[#222] hover:bg-[#333] hover:text-white hover:border-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#111] focus:ring-gray-500 transition-all disabled:opacity-50 group">
                            <span wire:loading.remove wire:target="requestChanges">Richiedi Modifiche</span>
                            <span wire:loading wire:target="requestChanges" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Invio...
                            </span>
                        </button>
                        
                        <button wire:click="approve" wire:loading.attr="disabled" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent shadow-lg text-sm font-medium rounded-xl text-[#111] bg-[#f1c40f] hover:bg-[#d4ac0d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#111] focus:ring-[#f1c40f] transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:hover:translate-y-0 disabled:transform-none">
                            <span wire:loading.remove wire:target="approve" class="flex items-center">
                                <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Approva Post
                            </span>
                            <span wire:loading wire:target="approve" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-[#111]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Approvazione...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
