<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverWelcomePasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $driver;

    public string $temporaryPassword;

    public function __construct(object|array $driver, string $temporaryPassword)
    {
        $this->driver = is_array($driver) ? (object) $driver : $driver;
        $this->temporaryPassword = $temporaryPassword;
    }

    public function build()
    {
        return $this->subject('Your Driver Account Password')
            ->view('emails.driver_welcome_password');
    }
}
