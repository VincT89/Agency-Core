<?php

namespace App\Http\Controllers;

use App\Models\Shooting\Shoot;
use App\Helpers\ShootingRouteResolver;

class ShootRedirectController extends Controller
{
    public function index()
    {
        return redirect()->to(ShootingRouteResolver::indexRouteFor(auth()->user()));
    }

    public function show(Shoot $shoot)
    {
        return redirect()->to(ShootingRouteResolver::showRouteFor(auth()->user(), $shoot));
    }
}
