<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * @param  object|array  $order  Order data (from Firebase or Eloquent)
     */
    public function __construct(object|array $order)
    {
        $this->order = is_array($order) ? (object) $order : $order;
    }

    public function build()
    {
        return $this->subject('Your Order Receipt')
                    ->view('emails.order_receipt');
    }
}
