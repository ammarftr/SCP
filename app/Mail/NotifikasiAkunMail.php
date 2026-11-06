<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotifikasiAkunMail extends Mailable
{
    /** @param string[] $baris paragraf isi email */
    public function __construct(
        public string $nama,
        public string $judul,
        public array $baris,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->judul . ' - SCP SMK Negeri 1 Bantul');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notifikasi-akun');
    }
}
