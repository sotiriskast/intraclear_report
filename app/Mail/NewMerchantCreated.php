<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewMerchantCreated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The merchant data.
     *
     * @var object
     */
    public $merchant;

    /**
     * The internal merchant ID.
     *
     * @var int
     */
    public $merchantId;

    /**
     * Create a new message instance.
     *
     * @param object $merchant
     * @param int $merchantId
     * @return void
     */
    public function __construct($merchant, int $merchantId)
    {
        $this->merchant = $merchant;
        $this->merchantId = $merchantId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('New Merchant Created')
            ->markdown('emails.settlements.new-merchant-created')
            ->with([
                'merchantId' => $this->merchantId,
                'accountId' => $this->merchant->id,
                'name' => $this->merchant->corp_name,
                'email' => $this->merchant->email,
                'phone' => $this->merchant->phone,
                'isActive' => $this->merchant->active,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);
    }
}
