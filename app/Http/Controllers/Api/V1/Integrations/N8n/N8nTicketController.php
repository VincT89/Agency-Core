<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tickets\Actions\CreateTicketFromN8nAction;
use App\Support\ApiResponse;
class N8nTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateTicketFromN8nAction $action
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
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'n8n_execution_id' => ['nullable', 'string', 'max:255'],
            'marketing_project_id' => ['nullable', 'exists:marketing_projects,id'],
            'social_post_id' => ['nullable', 'exists:social_posts,id'],
            'source' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        $ticket = $this->action->execute($data);

        return $this->success([
            'ticket_id' => $ticket->id,
            'code' => $ticket->code,
        ], 201);
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
