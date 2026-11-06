<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class KodeOtpMail extends Mailable
{
    public function __construct(
        public string $nama,
        public string $kode,
        public string $judul,
        public int $menit,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->judul . ' - SCP SMK Negeri 1 Bantul');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.kode-otp');
    }
}
