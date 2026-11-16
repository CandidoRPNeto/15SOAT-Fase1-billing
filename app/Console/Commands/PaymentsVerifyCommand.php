<?php

namespace App\Console\Commands;

use App\Application\Billing\VerifyPayment;
use Illuminate\Console\Command;

/**
 * Scheduled every minute (routes/console.php): polls Mercado Pago for every
 * pending payment.
 *
 * Example: php artisan payments:verify
 */
class PaymentsVerifyCommand extends Command
{
    protected $signature = 'payments:verify';

    protected $description = 'Confirm pending payments with the payment provider';

    public function handle(VerifyPayment $verify): int
    {
        $this->info('confirmed='.$verify->verifyPending());

        return self::SUCCESS;
    }
}
