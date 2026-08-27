<?php

namespace App\Http\Controllers;

use App\Services\SpraContactApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(Request $request, SpraContactApiClient $client): Response
    {
        return Inertia::render('Contact', [
            'success' => (bool) $request->session()->get('contact_success'),
            'error' => (bool) $request->session()->get('contact_error'),
            'categories' => $client->getCategories(),
        ]);
    }

    public function store(Request $request, SpraContactApiClient $client): RedirectResponse
    {
        $categories = $client->getCategories();

        $validated = $request->validate([
            'referral' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255', Rule::in(array_column($categories, 'id'))],
            'name' => ['required', 'string', 'max:255'],
            'name_kana' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'tel' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy_agree' => ['accepted'],
            'not_sales_agree' => ['accepted'],
        ]);

        $categoryName = $this->findCategoryName($categories, $validated['category']);

        $messageBody = $validated['message'];
        if (! empty($validated['name_kana'])) {
            $messageBody = "ふりがな: {$validated['name_kana']}\n".$messageBody;
        }
        if (! empty($validated['referral'])) {
            $messageBody = "当サイトを知ったきっかけ: {$validated['referral']}\n".$messageBody;
        }

        $submitted = $client->submit([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['tel'],
            'company' => $validated['company'] ?? null,
            'contact_category_id' => $validated['category'],
            'subject' => $categoryName ?? 'お問い合わせ',
            'message' => $messageBody,
            'page_url' => $request->url(),
            'visitor_ip' => (string) $request->ip(),
            'visitor_user_agent' => (string) $request->userAgent(),
        ]);

        if (! $submitted) {
            return Redirect::route('contact.show')->with('contact_error', true);
        }

        return Redirect::route('contact.show')->with('contact_success', true);
    }

    /**
     * @param  array<int, array{id: string, name: string}>  $categories
     */
    private function findCategoryName(array $categories, string $categoryId): ?string
    {
        foreach ($categories as $category) {
            if ((string) $category['id'] === $categoryId) {
                return $category['name'];
            }
        }

        return null;
    }
}
