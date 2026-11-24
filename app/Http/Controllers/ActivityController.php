<?php

namespace App\Http\Controllers;

use App\Models\Activities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    //

    public function index(Request $request)
    {
        try {
            $user  = $request->user();
            $limit = min(max((int) $request->query('limit', 10), 1), 50);

            $query = Activities::with('user:id,first_name,last_name');

            switch ((int) $user->role_id) {
                case 1: // admin: everything
                    break;
                case 2: // manager: own department only
                    if (!$user->department_id) {
                        return response()->json([]); // never fall through to "all"
                    }
                    $query->where('department_id', $user->department_id);
                    break;
                default: // employee: only their own actions
                    $query->where('user_id', $user->id);
            }

            $items = $query->latest('created_at')->latest('id')
                ->limit($limit)
                ->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'action'        => $a->action,
                    'subject_type'  => $this->subjectKey($a->subject_type),
                    'subject_id'    => $a->subject_id,
                    'subject_label' => $a->subject_label,
                    'created_at'    => $a->created_at?->toIso8601String(),
                    'actor'         => $a->user ? [
                        'id'         => $a->user->id,
                        'first_name' => $a->user->first_name,
                        'last_name'  => $a->user->last_name,
                    ] : null,
                ]);

            return response()->json($items, 200);
        } catch (\Throwable $e) {
            Log::error('recent activity failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load recent activity.'], 500);
        }
    }

    /** Works whether subject_type holds a class name or a morph-map alias. */
    private function subjectKey(?string $type): string
    {
        $base = strtolower(class_basename((string) $type));

        return match (true) {
            str_contains($base, 'leave')   => 'leave',
            str_contains($base, 'task')    => 'task',
            str_contains($base, 'project') => 'project',
            default                        => 'other',
        };
    }
}
