<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\View\View;
class DashboardController extends Controller
{

    public function __invoke(): View
    {
        $user = auth()->user();
        $data = [];

        if ($user->canManageSystem()) {
            $data = $this->getAdminData($user);
        } elseif ($user->isAdministration()) {
            $data = $this->getAdministrationData($user);
        } else {
            // Include ruoli operativi: Developer, Marketing, Photographer, GraphicDesigner
            $data = $this->getWorkspaceData($user);
        }

        return view('dashboard', $data);
    }

    private function getAdminData($user): array
    {
        return [
            'activeClients'    => Client::where('status', 'active')->count(),
            'openTicketsCount' => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            'overdueInvoices'  => Invoice::where('status', 'overdue')->count(),
            'expiringTasks'    => Task::whereBetween('due_date', [today(), today()->addDays(7)])
                                    ->where('status', '!=', 'done')
                                    ->orderBy('due_date', 'asc')
                                    ->with(['project', 'assignee'])
                                    ->limit(5)->get(),
            'recentActivity'   => AuditLog::with('user')->latest()->limit(8)->get(),
            'recentTickets'    => Ticket::with(['project', 'assignee'])->latest()->limit(5)->get(),
            'upcomingEvents'   => \App\Models\CalendarEvent::where('start_at', '>=', now())
                                    ->orderBy('start_at', 'asc')
                                    ->limit(5)->get(),
            'weeklyEvents'     => \App\Models\CalendarEvent::whereBetween('start_at', [now(), now()->addDays(7)])
                                    ->orderBy('start_at', 'asc')
                                    ->get(),
        ];
    }

    private function getAdministrationData($user): array
    {
        $thirtyDaysAgo = now()->subDays(30);
        $totalCollected30d = \App\Models\Payment::where('payment_date', '>=', $thirtyDaysAgo)->sum('amount');
        $totalOutstanding = \App\Models\Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
                                ->sum(\Illuminate\Support\Facades\DB::raw('total - paid_total'));

        return [
            'totalCollected30d'    => $totalCollected30d,
            'totalOutstanding'     => $totalOutstanding,
            'openInvoicesCount'    => Invoice::whereIn('status', ['draft', 'issued', 'partially_paid'])->count(),
            'overdueInvoicesCount' => Invoice::where('status', 'overdue')->count(),
            'recentPayments'       => \App\Models\Payment::with('invoice.client')->latest()->limit(5)->get(),
            'upcomingDeadlines'    => Invoice::where('status', 'issued')
                                        ->whereDate('due_date', '<=', now()->addDays(14))
                                        ->orderBy('due_date')->limit(5)->get(),
        ];
    }

    private function getWorkspaceData($user): array
    {
        return [
            'overdueTasks' => Task::assignedTo($user)
                                ->open()
                                ->overdue()
                                ->with(['project'])
                                ->orderBy('due_date', 'asc')
                                ->get(),

            'dueSoonTasks' => Task::assignedTo($user)
                                ->open()
                                ->dueSoon(7) // Tra oggi e 7 giorni
                                ->with(['project'])
                                ->orderBy('due_date', 'asc')
                                ->get(),

            'otherTasks'   => Task::assignedTo($user)
                                ->open()
                                ->where(function ($q) {
                                    $q->whereNull('due_date')
                                      ->orWhereDate('due_date', '>', today()->addDays(7));
                                })
                                ->with(['project'])
                                ->orderBy('created_at', 'desc')
                                ->limit(10)
                                ->get(),

            'openTickets'  => Ticket::query()
                                ->whereIn('status', ['open', 'in_progress'])
                                ->with(['project'])
                                ->latest()
                                ->limit(5)
                                ->get(),
                                
            'upcomingEvents' => \App\Models\CalendarEvent::query()
                                ->where('start_at', '>=', now())
                                ->orderBy('start_at')
                                ->limit(5)
                                ->get(),
                                
            'recentAttachments' => \App\Models\Attachment::where('uploaded_by', $user->id)
                                ->latest()
                                ->limit(5)
                                ->get(),

            'recentShoots' => \App\Models\Shooting\Shoot::query()
                                ->where('created_by', $user->id)
                                ->with(['project'])
                                ->latest()
                                ->limit(5)
                                ->get(),
        ];
    }
}