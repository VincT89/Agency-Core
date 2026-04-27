<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se l'utente non è loggato, lasciamo che sia il middleware `auth` a gestire
        if (! $user) {
            return $next($request);
        }

        // Se la password è già cambiata, si procede normalmente
        if ($user->password_changed_at !== null) {
            return $next($request);
        }

        // Whitelist delle route ammesse durante il forced password change
        // Permettiamo il reset form (GET/POST) e il logout
        $allowedRoutes = [
            'password.setup',
            'password.setup.update',
            'logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes)) {
            return $next($request);
        }

        // In tutti gli altri casi, redirigiamo al form di setup password
        return redirect()->route('password.setup');
    }
}
