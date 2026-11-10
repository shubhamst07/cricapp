<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $senderName = $this->data['name'] ?? 'Visitor';
        $topic = $this->data['inquiry_type'] ?? 'General Inquiry';

        return new Envelope(
            subject: "[LiveCricket] New Contact Message: {$topic} from {$senderName}",
            replyTo: [
                new Address(
                    $this->data['email'] ?? 'noreply@livecricket.app',
                    $senderName
                ),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-inquiry',
            with: [
                'name' => $this->data['name'] ?? '',
                'email' => $this->data['email'] ?? '',
                'phone' => $this->data['phone'] ?? 'Not provided',
                'inquiryType' => $this->data['inquiry_type'] ?? 'General',
                'messageBody' => $this->data['message'] ?? '',
                'submittedAt' => now()->format('d M Y, h:i A (T)'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
