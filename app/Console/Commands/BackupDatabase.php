<?php

namespace App\Console\Commands;

use App\Support\RequiredStorage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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

        $temporaryPath = null;
        $stream = null;

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
                    $dumpCommand = ['mariadb-dump', '--single-transaction', '--quick', '--skip-lock-tables', '-h', $host, '-P', (string) $port, '-u', $username, $database];
                    $environment = ['MYSQL_PWD' => $password];
                    break;
                case 'pgsql':
                    $host = config('database.connections.'.$connection.'.host');
                    $port = config('database.connections.'.$connection.'.port');
                    $username = config('database.connections.'.$connection.'.username');
                    $password = config('database.connections.'.$connection.'.password');
                    $dumpCommand = ['pg_dump', '-h', $host, '-p', (string) $port, '-U', $username, $database];
                    $environment = ['PGPASSWORD' => $password];
                    break;
                case 'sqlite':
                    $dumpCommand = ['sqlite3', $database, '.dump'];
                    $environment = [];
                    break;
                default:
                    $this->error('Database type not supported for backup: '.$connection);

                    return Command::FAILURE;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'erp-backup-');
            if ($temporaryPath === false) {
                throw new \RuntimeException('Could not create the backup temporary file.');
            }
            $stream = fopen($temporaryPath, 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Could not open the backup temporary file.');
            }
            $process = $this->dumpProcess($dumpCommand, $environment);
            $process->setTimeout(1800);
            $process->run(function (string $type, string $buffer) use ($stream, $process): void {
                if ($type === Process::OUT) {
                    if (fwrite($stream, $buffer) !== strlen($buffer)) {
                        throw new \RuntimeException('Could not write the database dump.');
                    }
                    $process->clearOutput();
                }
            });
            fclose($stream);
            $stream = null;
            if (! $process->isSuccessful() || filesize($temporaryPath) === 0) {
                throw new \RuntimeException('Failed to generate database dump: '.$process->getErrorOutput());
            }
            RequiredStorage::putFile('s3', $filename, $temporaryPath);

            $this->info('Database backup completed successfully: '.$filename);

            // Clean up old backups (keep last 30 days)
            $this->cleanupOldBackups();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @param list<string> $command
     * @param  array<string, string|null>  $environment
     */
    protected function dumpProcess(array $command, array $environment): Process
    {
        return new Process($command, base_path(), $environment);
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
