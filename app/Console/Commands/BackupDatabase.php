<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the database to S3 storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        try {
            $database = config('database.connections.'.config('database.default').'.database');
            $filename = 'backups/database/'.$database.'_'.Carbon::now()->format('Y-m-d_H-i-s').'.sql';

            // Determine the dump command based on database type
            $connection = config('database.default');
            $dumpCommand = '';

            switch ($connection) {
                case 'mysql':
                case 'mariadb':
                    $host = config('database.connections.'.$connection.'.host');
                    $port = config('database.connections.'.$connection.'.port');
                    $username = config('database.connections.'.$connection.'.username');
                    $password = config('database.connections.'.$connection.'.password');
                    $dumpCommand = "mysqldump -h {$host} -P {$port} -u {$username} -p{$password} {$database}";
                    break;
                case 'pgsql':
                    $host = config('database.connections.'.$connection.'.host');
                    $port = config('database.connections.'.$connection.'.port');
                    $username = config('database.connections.'.$connection.'.username');
                    $password = config('database.connections.'.$connection.'.password');
                    $dumpCommand = "PGPASSWORD={$password} pg_dump -h {$host} -p {$port} -U {$username} {$database}";
                    break;
                case 'sqlite':
                    $dumpCommand = "sqlite3 {$database} .dump";
                    break;
                default:
                    $this->error('Database type not supported for backup: '.$connection);

                    return Command::FAILURE;
            }

            // Execute the dump command
            $output = shell_exec($dumpCommand);

            if ($output === null) {
                $this->error('Failed to generate database dump');

                return Command::FAILURE;
            }

            // Store the backup
            Storage::disk('s3')->put($filename, $output);

            $this->info('Database backup completed successfully: '.$filename);

            // Clean up old backups (keep last 30 days)
            $this->cleanupOldBackups();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Clean up old backups (keep last 30 days)
     */
    private function cleanupOldBackups(): void
    {
        $this->info('Cleaning up old backups...');

        $files = Storage::disk('s3')->files('backups/database');
        $cutoffDate = Carbon::now()->subDays(30);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::disk('s3')->lastModified($file));

            if ($lastModified->lt($cutoffDate)) {
                Storage::disk('s3')->delete($file);
                $this->info('Deleted old backup: '.$file);
            }
        }

        $this->info('Backup cleanup completed.');
    }
}
