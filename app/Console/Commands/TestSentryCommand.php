<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sentry\Laravel\Facades\Sentry;

class TestSentryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentry:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Sentry error tracking integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Sentry integration...');

        try {
            // Test capturing a message
            Sentry::captureMessage('Test message from YKP Dashboard', 'info');
            $this->info('✅ Message sent to Sentry successfully!');

            // Test capturing an exception
            $this->warn('Sending test exception...');
            throw new \Exception('Test exception from YKP Dashboard');
        } catch (\Exception $e) {
            Sentry::captureException($e);
            $this->error('✅ Exception captured and sent to Sentry!');
        }

        // Test user context
        Sentry::configureScope(function ($scope) {
            $scope->setUser([
                'id' => 'test-user',
                'email' => 'test@ykp.com',
                'role' => 'headquarters',
            ]);
        });

        $this->info('✅ User context configured!');

        // Test breadcrumbs
        Sentry::addBreadcrumb([
            'message' => 'Test breadcrumb',
            'category' => 'test',
            'level' => 'info',
        ]);

        $this->info('✅ Breadcrumb added!');

        $this->newLine();
        $this->info('Sentry test completed successfully!');
        $this->warn('Note: Check your Sentry dashboard to verify the events were received.');

        return Command::SUCCESS;
    }
}