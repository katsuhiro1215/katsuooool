<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmitted extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string|bool>  $data
     */
    public function __construct(public readonly array $data) {}

    public function build(): self
    {
        return $this->subject('【katsuooool】お問い合わせがありました')
            ->view('emails.contact-form-submitted')
            ->replyTo((string) $this->data['email'], (string) $this->data['name']);
    }
}
