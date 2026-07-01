<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

class OperationalAnalyticsService
{
    /**
     * Get monthly operational data (Tasks and Tickets completed) for charts
     */
    public function getMonthlyData(int $months = 12): array
    {
        $startDate = now()->startOfYear();
        $months = 12;
        
        $monthsArray = [];
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $monthsArray[$key] = [
                'tasks' => 0,
                'tickets' => 0,
            ];
            $labels[] = ucfirst($date->isoFormat('MMM YYYY'));
        }

        // Closed Tasks
        // Assuming 'done' status or based on updated_at/completed_at. Let's use updated_at for when it was marked done.
        $dateFormat = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', updated_at)" : "DATE_FORMAT(updated_at, '%Y-%m')";
        $tasks = Task::where('status', 'done')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw("{$dateFormat} as month, COUNT(*) as total")
            ->groupBy('month')
            ->get();

        foreach ($tasks as $task) {
            if (isset($monthsArray[$task->month])) {
                $monthsArray[$task->month]['tasks'] = (int) $task->total;
            }
        }

        // Closed Tickets
        // Assuming 'closed' or 'resolved' status. Let's use 'closed' or 'done'
        $dateFormat2 = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', updated_at)" : "DATE_FORMAT(updated_at, '%Y-%m')";
        $tickets = Ticket::whereIn('status', ['closed', 'resolved', 'done'])
            ->where('updated_at', '>=', $startDate)
            ->selectRaw("{$dateFormat2} as month, COUNT(*) as total")
            ->groupBy('month')
            ->get();

        foreach ($tickets as $ticket) {
            if (isset($monthsArray[$ticket->month])) {
                $monthsArray[$ticket->month]['tickets'] = (int) $ticket->total;
            }
        }

        $tasksData = [];
        $ticketsData = [];

        foreach ($monthsArray as $data) {
            $tasksData[] = $data['tasks'];
            $ticketsData[] = $data['tickets'];
        }

        return [
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Task Completati',
                    'data' => $tasksData
                ],
                [
                    'name' => 'Ticket Chiusi',
                    'data' => $ticketsData
                ]
            ]
        ];
    }
}
