<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyDueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:due-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica gli utenti sulle task in scadenza il giorno successivo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = today()->addDay();

        $tasks = Task::query()
            ->whereNotNull('assigned_to')
            ->whereDate('due_date', $tomorrow)
            ->where('status', '!=', 'done')
            ->with('assignee')
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $assignee = $task->assignee;
            
            if (!$assignee) {
                continue;
            }

            // Regola anti-duplicato per oggi
            $alreadyNotifiedToday = DB::table('notifications')
                ->where('notifiable_id', $assignee->id)
                ->where('notifiable_type', get_class($assignee))
                ->where('data->type', 'task_due_soon')
                ->where('data->task_id', $task->id)
                ->whereDate('created_at', today())
                ->exists();

            if (!$alreadyNotifiedToday) {
                $assignee->notify(new TaskDueSoonNotification($task));
                $count++;
            }
        }

        $this->info("Inviate {$count} notifiche per task in scadenza.");
    }
}
