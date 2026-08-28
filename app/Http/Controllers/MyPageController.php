<?php

namespace App\Http\Controllers;

use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyPageController extends Controller
{
    private const FREE_HISTORY_LIMIT = 5;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();

        $query = ToolEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        $toolEvents = $isPremium
            ? $query->paginate(20)
            : ['data' => $query->limit(self::FREE_HISTORY_LIMIT)->get(), 'links' => []];

        return Inertia::render('MyPage', [
            'toolEvents' => $toolEvents,
            'isPremium' => $isPremium,
            'freeHistoryLimit' => self::FREE_HISTORY_LIMIT,
        ]);
    }
}
