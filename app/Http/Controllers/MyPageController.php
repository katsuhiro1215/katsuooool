<?php

namespace App\Http\Controllers;

use App\Models\ToolEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyPageController extends Controller
{
    public function index(Request $request): Response
    {
        $toolEvents = ToolEvent::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('MyPage', [
            'toolEvents' => $toolEvents,
        ]);
    }
}
