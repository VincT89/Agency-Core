<div style="margin-bottom:16px;color:var(--text3);font-size:13px">
    Una volta eliminato il tuo account, tutte le sue risorse e dati verranno eliminati permanentemente. Prima di eliminare il tuo account, per favore scarica eventuali dati o informazioni che desideri conservare.
</div>
<button type="button" class="btn btn-g" style="color:var(--red);border-color:rgba(200,16,46,0.3)" onclick="document.getElementById('confirm-user-deletion').style.display='block'">
    Elimina Account
</button>

<div id="confirm-user-deletion" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
    <form method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <div style="margin-bottom:12px;font-weight:600;color:var(--text)">Sei sicuro di voler eliminare l'account?</div>
        
        <div class="form-row full">
            <x-form-group label="Password" name="password" required>
                <input id="password" name="password" type="password" class="form-in @error('password', 'userDeletion') is-invalid @enderror" placeholder="La tua password per confermare" />
                @error('password', 'userDeletion')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </x-form-group>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="button" class="btn btn-g" onclick="document.getElementById('confirm-user-deletion').style.display='none'">Annulla</button>
            <button type="submit" class="btn btn-p" style="background:var(--red);border-color:var(--red)">Conferma ed Elimina</button>
        </div>
    </form>
</div>