<?php

use Illuminate\Support\Facades\Schedule;

// Polling do Mercado Pago (sem webhook público no kind — ver README).
Schedule::command('payments:verify')->everyMinute()->withoutOverlapping();
