<x-guest-layout title="Accedi — Sodano Consulting">
  <div class="login-shell">

    {{-- SINISTRA: brand --}}
    <div class="login-left">
      <canvas id="bg-canvas" class="login-canvas"></canvas>
      <div class="login-left-top">
        <div class="login-logo">
          <img src="{{ asset('images/logo.png') }}" alt="Sodano Consulting">
          <span class="login-logo-name">Sodano Consulting</span>
        </div>
        <div class="login-eyebrow">Gestionale interno</div>
        <div class="login-title">
          Strategia,<br>Innovazione,<br>
          <strong>Performance.</strong>
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
        <div class="login-form-eyebrow">Bentornato</div>
        <div class="login-form-title">Accedi al tuo<br>account</div>
      </div>

      {{-- Session error (credenziali errate) --}}
      @if(session('status'))
        <div class="flash flash-error login-flash">
          {{ session('status') }}
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="form-g login-form-g">
          <div class="form-lbl">Email aziendale</div>
          <input
            type="email"
            name="email"
            class="form-in @error('email') is-invalid @enderror"
            value="{{ old('email') }}"
            placeholder="nome@sodanoconsulting.it"
            required
            autofocus
            autocomplete="email"
          >
          @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Password --}}
        <div class="form-g login-form-g last">
          <div class="form-lbl">Password</div>
          <input
            type="password"
            name="password"
            class="form-in @error('password') is-invalid @enderror"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
          @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Remember me --}}
        <div class="login-remember">
          <input type="checkbox" name="remember" id="remember" class="login-checkbox">
          <label for="remember" class="login-checkbox-lbl">
            Ricordami
          </label>
        </div>

        <div class="login-form-footer">
          <div class="login-form-hint">
            Problemi di accesso?<br>Contatta l'amministratore.
          </div>
          <button type="submit" class="btn btn-p">Entra →</button>
        </div>
      </form>

    </div>
  </div>
</x-guest-layout>
