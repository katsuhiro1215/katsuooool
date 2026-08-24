<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Contact', [
            'success' => (bool) $request->session()->get('contact_success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referral' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'name_kana' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'tel' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy_agree' => ['accepted'],
            'not_sales_agree' => ['accepted'],
        ]);

        Mail::to((string) config('mail.from.address'))->send(new ContactFormSubmitted($validated));

        return Redirect::route('contact.show')->with('contact_success', true);
    }
}
