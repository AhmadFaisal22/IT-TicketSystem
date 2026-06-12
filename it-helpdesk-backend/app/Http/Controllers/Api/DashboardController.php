<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->isItStaff(), 403);

        $validated = $request->validate(['range' => 'sometimes|integer|min:1|max:365']);
        $range = $validated['range'] ?? 30;
        $from = now()->subDays($range);

        $statusCounts = Ticket::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $priorityCounts = Ticket::select('priority', DB::raw('count(*) as count'))
            ->whereNotIn('status', ['resolved', 'closed'])
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $departmentCounts = Ticket::select('department_id', DB::raw('count(*) as count'))
            ->with('department:id,name,name_zh')
            ->whereBetween('created_at', [$from, now()])
            ->groupBy('department_id')
            ->get()
            ->map(fn ($r) => [
                'department' => $r->department,
                'count' => $r->count,
            ]);

        $itStaffLoad = User::where('role', 'it_staff')
            ->select('id', 'name', 'avatar')
            ->withCount(['assignedTickets as open_count' => fn ($q) =>
                $q->whereNotIn('status', ['resolved', 'closed'])
            ])
            ->get();

        // Database-agnostic: compute avg resolution in PHP to support both SQLite (dev) and PostgreSQL (prod)
        $resolvedTickets = Ticket::whereNotNull('resolved_at')
            ->whereBetween('created_at', [$from, now()])
            ->select(['created_at', 'resolved_at'])
            ->get();

        $avgResolutionHours = $resolvedTickets->isNotEmpty()
            ? $resolvedTickets->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60
            : 0;

        $trendsRaw = Ticket::whereBetween('created_at', [$from, now()])
            ->selectRaw("DATE(created_at) as date, count(*) as created")
            ->groupByRaw("DATE(created_at)")
            ->orderBy('date')
            ->get();

        $resolvedTrends = Ticket::whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, now()])
            ->selectRaw("DATE(resolved_at) as date, count(*) as resolved")
            ->groupByRaw("DATE(resolved_at)")
            ->orderBy('date')
            ->pluck('resolved', 'date');

        $trends = $trendsRaw->map(fn ($r) => [
            'date' => $r->date,
            'created' => $r->created,
            'resolved' => $resolvedTrends[$r->date] ?? 0,
        ]);

        return response()->json([
            'status_counts'       => $statusCounts,
            'priority_counts'     => $priorityCounts,
            'department_counts'   => $departmentCounts,
            'it_staff_load'       => $itStaffLoad,
            'avg_resolution_hours'=> round($avgResolutionHours ?? 0, 1),
            'trends'              => $trends,
            'total_open'          => $statusCounts->get('open', 0) + $statusCounts->get('in_progress', 0),
        ]);
    }

    public function sla(Request $request): JsonResponse
    {
        abort_unless($request->user()->isItStaff(), 403);

        $validated = $request->validate(['range' => 'sometimes|integer|min:1|max:365']);
        $range = $validated['range'] ?? 30;
        $from = now()->subDays($range);

        $total = Ticket::whereBetween('created_at', [$from, now()])->count();

        $responseBreached = Ticket::where('sla_response_breached', true)
            ->whereBetween('created_at', [$from, now()])->count();

        $resolutionBreached = Ticket::where('sla_resolution_breached', true)
            ->whereBetween('created_at', [$from, now()])->count();

        $currentlyBreached = Ticket::where(function ($q) {
                $q->where('sla_response_due_at', '<', now())
                  ->whereNull('first_response_at');
            })
            ->orWhere(function ($q) {
                $q->where('sla_resolution_due_at', '<', now())
                  ->whereNotIn('status', ['resolved', 'closed']);
            })
            ->count();

        $byPriority = Ticket::select('priority',
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN sla_resolution_breached THEN 1 ELSE 0 END) as breached')
            )
            ->whereBetween('created_at', [$from, now()])
            ->groupBy('priority')
            ->get();

        $byDepartment = Ticket::select('department_id',
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN sla_resolution_breached THEN 1 ELSE 0 END) as breached')
            )
            ->with('department:id,name,name_zh')
            ->whereBetween('created_at', [$from, now()])
            ->groupBy('department_id')
            ->get()
            ->map(fn ($r) => [
                'department'      => $r->department,
                'total'           => $r->total,
                'breached'        => $r->breached,
                'compliance_rate' => $r->total > 0
                    ? round((($r->total - $r->breached) / $r->total) * 100, 1)
                    : 100,
            ]);

        return response()->json([
            'total_tickets'      => $total,
            'response_breached'  => $responseBreached,
            'resolution_breached'=> $resolutionBreached,
            'currently_at_risk'  => $currentlyBreached,
            'overall_compliance' => $total > 0
                ? round((($total - $resolutionBreached) / $total) * 100, 1)
                : 100,
            'by_priority'        => $byPriority,
            'by_department'      => $byDepartment,
        ]);
    }
}
