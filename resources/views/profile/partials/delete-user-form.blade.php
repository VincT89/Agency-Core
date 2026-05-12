<div x-data="{ confirmingUserDeletion: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <div class="u-mb-md u-text-muted u-text-sm">
        Una volta eliminato il tuo account, tutte le sue risorse e dati verranno eliminati permanentemente. Prima di eliminare il tuo account, per favore scarica eventuali dati o informazioni che desideri conservare.
    </div>
    <button type="button" class="btn btn-g btn-danger-outline" @click="confirmingUserDeletion = true">
        Elimina Account
    </button>

    <div x-show="confirmingUserDeletion" class="u-mt-md u-pt-md u-border-t" x-cloak>
    <form method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <div class="u-mb-sm u-text-strong">Sei sicuro di voler eliminare l'account?</div>
        
        <div class="form-row full">
            <x-form-group label="Password" name="password" required>
                <input id="password" name="password" type="password" class="form-in @error('password', 'userDeletion') is-invalid @enderror" placeholder="La tua password per confermare" />
                @error('password', 'userDeletion')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </x-form-group>
        </div>

        <div class="u-flex u-gap-sm u-mt-md">
            <button type="button" class="btn btn-g" @click="confirmingUserDeletion = false">Annulla</button>
            <button type="submit" class="btn btn-p btn-danger">Conferma ed Elimina</button>
        </div>
    </form>
    </div>
</div>