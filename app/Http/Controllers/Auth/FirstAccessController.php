<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FirstAccessController extends Controller
{
    /**
     * Mostra il form del cambio password obbligatorio.
     */
    public function show(Request $request): View|RedirectResponse
    {
        // Se l'utente ha già impostato la password, lo mandiamo alla dashboard
        if ($request->user()->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }

        return view('auth.first-access');
    }

    /**
     * Valida ed esegue il cambio password, poi rigenera la sessione.
     */
    public function update(Request $request): RedirectResponse
    {
        // Se l'utente ha già impostato la password, lo mandiamo alla dashboard
        if ($request->user()->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }

        // Usiamo Password::defaults() se definiti in AppServiceProvider, o regole robuste
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        // Evitiamo session fixation
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password aggiornata con successo! Benvenuto.');
    }
}
