<?php

namespace App\Http\Controllers;

use App\Domain\Availability\Actions\RecordPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresenceController extends Controller
{
    public function state(Request $request, PresenceSummary $summary): JsonResponse
    {
        return response()->json($summary->personal($request->user(), hash('sha256', $request->session()->getId()),
            $request->session()->get('presence.login_at'), (bool) $request->session()->get('presence.login_prompt', false),
            $request->session()->get('presence.prompt_answered_on')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, RecordPresence $record, PresenceSummary $summary): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'start_early', 'heartbeat', 'pause', 'resume', 'extend', 'finish', 'defer'])],
            'availability_id' => ['nullable', 'required_if:action,start,start_early', 'integer', 'min:1'],
            'presence_id' => ['nullable', 'required_unless:action,start,start_early,defer', 'integer', 'min:1'],
        ]);
        if ($data['action'] === 'defer') {
            $request->session()->forget('presence.login_prompt');
            $request->session()->put('presence.prompt_answered_on', now()->toDateString());

            return $this->state($request, $summary);
        }
        $record->execute($request->user(), hash('sha256', $request->session()->getId()), $data['action'],
            isset($data['presence_id']) ? (int) $data['presence_id'] : null,
            isset($data['availability_id']) ? (int) $data['availability_id'] : null);
        if (in_array($data['action'], ['start', 'start_early'], true)) {
            $request->session()->forget('presence.login_prompt');
            $request->session()->put('presence.prompt_answered_on', now()->toDateString());
        }

        return $this->state($request, $summary);
    }
}
