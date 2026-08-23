<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function show(User $user): Response
    {
        $toolEvents = ToolEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('admin/users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified' => $user->email_verified_at !== null,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'passkey_count' => $user->passkeys()->count(),
            ],
            'toolEvents' => $toolEvents,
        ]);
    }
}
