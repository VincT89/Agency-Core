<?php

namespace App\Http\Controllers;

use App\Domain\Core\Actions\ProtectAdministratorAccess;
use App\Models\User;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    // Mostra il form del profilo utente
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'lastActiveAdministrator' => $request->user()->isLastActiveAdministrator(),
        ]);
    }

    // Aggiorna le informazioni del profilo utente
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    // Elimina l'account utente (Richiede conferma password)
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $blocked = app(ProtectAdministratorAccess::class)->execute($user, function (User $user) {
            if ($user->isLastActiveAdministrator()) {
                return back()->with('error', 'Non puoi eliminare l’unico amministratore attivo. Crea o attiva prima un altro amministratore.');
            }

            if ($user->hasExpenseHistory()) {
                return back()->with('error', 'Il tuo account è collegato allo storico di entrate e spese. Chiedi a un amministratore di disattivarlo mantenendo le registrazioni.');
            }

            /** Clear the actor before deletion so the audit entry cannot reference a deleted user. */
            Auth::logout();
            $user->delete();

            return null;
        });
        if ($blocked) {
            return $blocked;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
