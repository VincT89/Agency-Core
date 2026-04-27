<x-guest-layout title="Imposta Password — Sodano Consulting">
  <div class="login-shell">

    {{-- SINISTRA: brand --}}
    <div class="login-left">
      <canvas id="bg-canvas" class="login-canvas"></canvas>
      <div class="login-left-top">
        <div class="login-logo">
          <img src="{{ asset('images/logo.png') }}" alt="Sodano Consulting">
          <span class="login-logo-name">Sodano Consulting</span>
        </div>
        <div class="login-eyebrow">Onboarding Obbligatorio</div>
        <div class="login-title">
          Sicurezza,<br>Protezione,<br>
          <strong>Account.</strong>
        </div>
      </div>

      <div class="login-left-bottom">
        <div class="login-stat-strip">
          <div class="login-stat-cell">
            <div class="login-stat-val"><em>10</em>+</div>
            <div class="login-stat-lbl">Anni</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Dev</div>
            <div class="login-stat-lbl">Developer</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Mkt</div>
            <div class="login-stat-lbl">Marketing</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Sys</div>
            <div class="login-stat-lbl">System</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Sht</div>
            <div class="login-stat-lbl">Shooting</div>
          </div>
        </div>
        <div class="login-copy">© 2015–{{ date('Y') }} Sodano Consulting S.r.l.</div>
      </div>
    </div>

    {{-- DESTRA: form --}}
    <div class="login-right">

      <div class="login-form-top">
        <div class="login-form-eyebrow">Primo Accesso</div>
        <div class="login-form-title">Imposta la tua<br>Password</div>
        <p class="login-form-desc">
          Per motivi di sicurezza è obbligatorio impostare una nuova password personalizzata prima di poter accedere al gestionale.
        </p>
      </div>

      {{-- Session error --}}
      @if(session('status'))
        <div class="flash flash-error login-flash">
          {{ session('status') }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.setup.update') }}">
        @csrf

        {{-- Password --}}
        <div class="form-g login-form-g">
          <div class="form-lbl">Nuova Password</div>
          <input
            type="password"
            name="password"
            class="form-in @error('password') is-invalid @enderror"
            placeholder="Minimo 8 caratteri"
            required
            autofocus
            autocomplete="new-password"
          >
          @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Conferma Password --}}
        <div class="form-g login-form-g lg">
          <div class="form-lbl">Conferma Password</div>
          <input
            type="password"
            name="password_confirmation"
            class="form-in"
            placeholder="Ripeti password"
            required
            autocomplete="new-password"
          >
        </div>

        <div class="login-form-footer end">
          <button type="submit" class="btn btn-p">Salva e Accedi →</button>
        </div>
      </form>

      <div class="login-divider"></div>
      
      <form method="POST" action="{{ route('logout') }}" class="login-logout-form">
          @csrf
          <button type="submit" class="login-logout-btn">Annulla ed Esci</button>
      </form>

    </div>
  </div>
</x-guest-layout>
