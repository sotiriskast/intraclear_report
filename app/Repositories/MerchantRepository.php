<?php

namespace App\Repositories;

use App\Models\FeeType;
use App\Models\Merchant;
use App\Models\MerchantFee;
use App\Models\FeeHistory;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Support\Facades\DB;

class MerchantRepository{
    public function getMerchantIdByAccountId($accountId){
        return Merchant::query()->where('account_id', $accountId)->first()->id;
    }
}
