<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tickets\Actions\CreateTicketFromN8n;
use App\Http\Requests\Integrations\N8n\CreateTicketFromN8nRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class N8nTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateTicketFromN8n $action
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateTicketFromN8nRequest $request): JsonResponse
    {
        $result = $this->action->execute($request->validated());

        $statusCode = $result['created'] ? 201 : 200;

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_id' => $result['ticket']->id,
                'created' => $result['created'],
                'status' => $result['ticket']->status,
                'client_id' => $result['ticket']->client_id,
                'project_id' => $result['ticket']->project_id,
            ],
        ], $statusCode);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
