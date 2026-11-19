<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\CourseAnnouncement;

class CourseAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public CourseAnnouncement $announcement)
{
}

public function envelope(): Envelope
{
    return new Envelope(
        subject: "【{$this->announcement->course->name}】授業のお知らせ",
    );
}

public function content(): Content
{
    return new Content(
        view: 'emails.course-announcement',
    );
}

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
