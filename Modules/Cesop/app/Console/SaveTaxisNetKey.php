<?php

namespace Modules\Cesop\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SaveTaxisNetKey extends Command
{
    protected $signature = 'cesop:save-key';
    protected $description = 'Save the TaxisNet public key to storage';

    public function handle()
    {
        $publicKey = <<<'EOD'
-----BEGIN PGP PUBLIC KEY BLOCK-----
Version: Encryption Desktop 10.3.2 (Build 15238)

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
5C25gdGkDVpkSlrP8D4=
=pFBn
-----END PGP PUBLIC KEY BLOCK-----
EOD;

        Storage::put('taxisnet_public_key.asc', $publicKey);

        $this->info('TaxisNet public key saved to storage.');

        return 0;
    }
}
