<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\AccountingService;
use Illuminate\Console\Command;

class AccountingSnapshotCommand extends Command
{
    protected $signature = 'accounting:snapshot
                            {--date= : Date for snapshot (default: today)}
                            {--range= : Generate for date range (e.g., 7 for last 7 days)}';

    protected $description = 'Generate daily balance sheet snapshot';

    public function handle(AccountingService $accountingService): int
    {
        $this->info('Generating Daily Balance Sheet Snapshot');
        $this->info('=======================================');

        try {
            $dates = [];

            if ($this->option('range')) {
                $days = (int) $this->option('range');
                for ($i = $days - 1; $i >= 0; $i--) {
                    $dates[] = now()->subDays($i)->format('Y-m-d');
                }
            } else {
                $dates[] = $this->option('date') ?? today()->format('Y-m-d');
            }

            $results = [];

            foreach ($dates as $date) {
                $this->line("Processing: {$date}...");
                $sheet = $accountingService->generateDailyBalanceSheet($date);
                $results[] = $sheet;
            }

            $this->newLine();
            $this->info('Summary:');

            $tableData = [];
            foreach ($results as $sheet) {
                $status = $sheet->is_balanced ? '✓' : '✗';
                $tableData[] = [
                    $sheet->sheet_date->format('Y-m-d'),
                    number_format($sheet->total_assets, 0),
                    number_format($sheet->total_liabilities, 0),
                    number_format($sheet->total_equity, 0),
                    $sheet->transaction_count,
                    number_format($sheet->transaction_volume, 0),
                    number_format($sheet->fees_collected, 0),
                    $status,
                ];
            }

            $this->table(
                ['Date', 'Assets', 'Liabilities', 'Equity', 'Txns', 'Volume', 'Fees', 'OK'],
                $tableData
            );

            $unbalanced = array_filter($results, fn($s) => !$s->is_balanced);
            if (!empty($unbalanced)) {
                $this->newLine();
                $this->error('WARNING: ' . count($unbalanced) . ' day(s) have balance discrepancies!');
                return self::FAILURE;
            }

            $this->newLine();
            $this->info('All balance sheets verified successfully.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Snapshot generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
