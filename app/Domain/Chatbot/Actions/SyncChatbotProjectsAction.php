<?php

namespace App\Domain\Chatbot\Actions;

use App\Models\Client;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotProject;
use App\Domain\Chatbot\Support\ChatbotLabelMapper;

class SyncChatbotProjectsAction
{
    public function execute(Client $client, ChatbotClient $chatbotClient): void
    {
        $projects = $client->projects()
            ->withoutGlobalScopes()
            ->whereIn('status', ['active', 'on_hold'])
            ->get();

        foreach ($projects as $project) {
            ChatbotProject::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'chatbot_client_id' => $chatbotClient->id,
                    'client_id' => $client->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => ChatbotLabelMapper::status($project->status),
                    'description' => $project->description,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'source_created_at' => $project->created_at,
                    'source_updated_at' => $project->updated_at,
                    'synced_at' => now(),
                ]
            );
        }

        $validProjectIds = $projects->pluck('id');

        ChatbotProject::where('chatbot_client_id', $chatbotClient->id)
            ->whereNotIn('project_id', $validProjectIds)
            ->delete();
    }
}
