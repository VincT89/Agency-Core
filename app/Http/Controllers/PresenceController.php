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
        return response()->json($summary->personal($request->user(), hash('sha256', $request->session()->getId())))
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, RecordPresence $record, PresenceSummary $summary): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'heartbeat', 'pause', 'resume', 'extend', 'finish'])],
            'availability_id' => ['nullable', 'required_if:action,start', 'integer', 'min:1'],
            'presence_id' => ['nullable', 'required_unless:action,start', 'integer', 'min:1'],
        ]);
        $record->execute($request->user(), hash('sha256', $request->session()->getId()), $data['action'],
            isset($data['presence_id']) ? (int) $data['presence_id'] : null,
            isset($data['availability_id']) ? (int) $data['availability_id'] : null);

        return $this->state($request, $summary);
    }
}
