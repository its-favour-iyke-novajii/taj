<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
         Commands\UpdateCTR::class,
         Commands\UpdateGL::class,
         Commands\UpdateCustomers::class,
         Commands\UpdatePEP::class,
         Commands\UpdateNIP::class,
         Commands\UpdateFXInward::class,
         Commands\UpdateEbankingGL::class,
         Commands\PostgresNIPSync::class,
         Commands\UpdateAllTransactions::class,
         Commands\UpdateCTRTest::class,
         Commands\UpdateStatistics::class,
         Commands\StrUpdate::class,
         Commands\offLimitsUpdate::class,
         Commands\calloverTransactions::class,
         Commands\updateAgentsTransactions::class,
         Commands\UpdateAccounts::class,
         Commands\FlagStr::class,
         Commands\ArchiveSTR::class,
         Commands\UpdateStaffs::class,
         Commands\UpdateFTR::class,

    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }
}
