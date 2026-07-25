<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        refreshTestDatabase as protected traitRefreshTestDatabase;
    }

    public function createApplication(): Application
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');

        $app = parent::createApplication();

        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite.database', ':memory:');

        $this->moveMySqlMigrationsAside();

        return $app;
    }

    protected function refreshTestDatabase(): void
    {
        $this->traitRefreshTestDatabase();

        if (DB::connection()->getDriverName() !== 'mysql') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    private function moveMySqlMigrationsAside(): void
    {
        static $handled = false;
        if ($handled) {
            return;
        }
        $handled = true;

        $mysqlFiles = [
            '2026_02_19_053407_change_result_enum_lose_to_loss_in_support_tables.php',
            '2026_02_20_045241_change_type_enum_to_string_in_coin_transactions_table.php',
            '2026_02_22_050247_add_unsettled_to_game_matches_type.php',
        ];

        $backupDir = database_path('migrations/.mysql_backup');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        foreach ($mysqlFiles as $file) {
            $source = database_path('migrations/'.$file);
            $dest = $backupDir.'/'.$file;
            if (file_exists($source) && ! file_exists($dest)) {
                rename($source, $dest);
            }
        }

        register_shutdown_function(function () use ($backupDir) {
            foreach (glob($backupDir.'/*.php') as $file) {
                $dest = database_path('migrations/'.basename($file));
                if (file_exists($file)) {
                    rename($file, $dest);
                }
            }
            @rmdir($backupDir);
        });
    }
}
