<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;


class MerchantDashboard extends Component
{
    /**
     * The merchant ID.
     *
     * @var int|null
     */
    public $merchantId;

    /**
     * Create a new component instance.
     *
     * @param int|null $merchantId
     * @return void
     */
    public function __construct($merchantId = null)
    {
        $this->merchantId = $merchantId;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View
     */
    public function render(): View
    {
        return view('components.merchant-dashboard');
    }
}
