<?php

namespace App\Http\Controllers\Api\V1\Integrations\N8n;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tickets\Actions\CreateTicketFromN8nAction;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Ticket;
use Exception;

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
        //
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
