<?php

namespace App\Http\Controllers\Admin\Social;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\AgencySocialConnection;
use Illuminate\Support\Facades\Log;

class AgencyMetaOAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('facebook')
            ->scopes([
                'pages_manage_posts',
                'pages_read_engagement',
                'pages_show_list',
                'business_management',
                'instagram_basic',
                'instagram_content_publish',
                'instagram_manage_comments',
                'instagram_manage_insights'
            ])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            Log::error('Agency Meta OAuth Error', $request->all());
            return redirect()->route('admin.social.connections.index')
                ->with('error', 'Collegamento annullato o non riuscito: ' . $request->get('error_description'));
        }

        try {
            $socialUser = Socialite::driver('facebook')->user();
            
            $connection = AgencySocialConnection::updateOrCreate(
                [
                    'provider' => 'facebook',
                    'provider_user_id' => $socialUser->getId(),
                ],
                [
                    'provider_user_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'last_token_refresh_at' => now(),
                    'requires_reauth' => false,
                    'status' => \App\Enums\Social\AgencyConnectionStatus::Connected,
                    'connected_by' => auth()->id(),
                    'connected_at' => now(),
                    'scopes' => $socialUser->approvedScopes ?? [],
                ]
            );

            // Appena connesso, lanciamo un primo sync degli asset
            app(\App\Domain\Social\Actions\SyncMetaAssetsAction::class)->execute($connection);

            return redirect()->route('admin.social.connections.index')
                ->with('success', 'Account Meta collegato con successo!');

        } catch (\Exception $e) {
            Log::error('Agency Meta OAuth Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('admin.social.connections.index')
                ->with('error', 'Si è verificato un errore durante il collegamento con Meta.');
        }
    }
}
