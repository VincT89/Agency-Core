<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Console\Command;
use App\Domain\Core\Queries\TaskQuery;
use App\Models\Notification;
use Illuminate\Support\Str;
use App\Support\Monitoring\TracksSystemCommandRuns;
use Illuminate\Support\Facades\DB;

class NotifyDueTasks extends Command
{
    use TracksSystemCommandRuns;

    protected $signature = 'notify:due-tasks';


    protected $description = 'Send notifications for tasks due soon';


    public function handle(TaskQuery $taskQuery): int
    {
        return $this->runTracked($this->getName(), function () use ($taskQuery) {
            $tomorrow = today()->addDay();

            $tasks = $taskQuery->forSystemBatch()
                ->whereNotNull('assigned_to')
                ->whereDate('due_date', $tomorrow)
                ->where('status', '!=', 'done')
                ->with('assignee')
                ->get();

            $count = 0;

            foreach ($tasks as $task) {
                $assignee = $task->assignee;
                
                if (!$assignee || $assignee->status !== 'active') {
                    continue;
                }

                // Evita l'invio multiplo della stessa notifica nello stesso giorno
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
            return self::SUCCESS;
        });
    }
}
