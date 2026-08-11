<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    public function show(User $user)
    {
        return [
            'user' => $user,
            'counts' => [
                'accounts' => $user->accounts()->count(),
                'transactions' => $user->transactions()->count(),
                'bills' => $user->bills()->count(),
                'debts' => $user->debts()->count(),
                'subscriptions' => $user->subscriptions()->count(),
                'has_budget' => $user->budget()->exists(),
                'categories' => $user->categories()->count(),
            ],
        ];
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'sometimes|in:user,admin',
            'is_active' => 'sometimes|boolean',
        ]);

        abort_if(
            $user->id === $request->user()->id && (array_key_exists('role', $data) || array_key_exists('is_active', $data)),
            422,
            'You cannot change your own role or active status.'
        );

        $user->update($data);

        AuditLog::record(
            $request->user(),
            array_key_exists('is_active', $data) ? ($data['is_active'] ? 'user.activated' : 'user.suspended') : 'user.role_changed',
            $user,
            "Updated user {$user->email}",
            $data
        );

        return $user->fresh();
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');

        AuditLog::record($request->user(), 'user.deleted', null, "Deleted user {$user->email}", ['user_id' => $user->id]);

        $user->delete();

        return response()->noContent();
    }
}
