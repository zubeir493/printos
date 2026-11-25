<?php

namespace App\Console\Commands;

use App\Support\RequiredStorage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    protected $description = 'Back up the database to the configured storage disk';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        $temporaryPath = null;
        $clientOptionsPath = null;
        $stream = null;

        try {
            $connection = DB::connection();
            $config = $connection->getConfig();
            $database = $connection->getDatabaseName();
            $disk = config('filesystems.backup_disk');
            $databaseName = Str::slug(pathinfo(str_replace('\\', '/', $database), PATHINFO_FILENAME), '_') ?: 'database';
            $filename = 'backups/database/'.$databaseName.'_'.Carbon::now()->format('Y-m-d_H-i-s').'.sql';

            switch ($connection->getDriverName()) {
                case 'mysql':
                case 'mariadb':
                    $clientOptionsPath = tempnam(sys_get_temp_dir(), 'erp-db-client-');
                    $password = addcslashes((string) $config['password'], "\\\"\n\r\t");
                    if ($clientOptionsPath === false || file_put_contents($clientOptionsPath, "[client]\npassword=\"{$password}\"\n") === false) {
                        throw new \RuntimeException('Could not create the database client options file.');
                    }
                    $endpoint = filled($config['unix_socket'] ?? null)
                        ? ['--socket='.$config['unix_socket']]
                        : ['--protocol=tcp', '--host='.$config['host'], '--port='.$config['port']];
                    $dumpCommand = [config('database.backup_mysql_binary'), '--defaults-file='.$clientOptionsPath, '--single-transaction', '--quick', '--skip-lock-tables', ...$endpoint, '--user='.$config['username'], '--', $database];
                    $environment = [];
                    break;
                case 'pgsql':
                    $dumpCommand = ['pg_dump', '-h', $config['host'], '-p', (string) $config['port'], '-U', $config['username'], '--', $database];
                    $environment = ['PGPASSWORD' => (string) $config['password']];
                    break;
                case 'sqlite':
                    $dumpCommand = ['sqlite3', $database, '.dump'];
                    $environment = [];
                    break;
                default:
                    $this->error('Database type not supported for backup: '.$connection->getDriverName());

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
            RequiredStorage::putFile($disk, $filename, $temporaryPath);

            $this->info('Database backup completed successfully: '.$filename);

            // Clean up old backups (keep last 30 days)
            $this->cleanupOldBackups($disk);

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
            if (is_string($clientOptionsPath) && is_file($clientOptionsPath)) {
                unlink($clientOptionsPath);
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
    private function cleanupOldBackups(string $disk): void
    {
        $this->info('Cleaning up old backups...');

        $storage = Storage::disk($disk);
        $files = $storage->files('backups/database');
        $cutoffDate = Carbon::now()->subDays(30);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($storage->lastModified($file));

            if ($lastModified->lt($cutoffDate)) {
                $storage->delete($file);
                $this->info('Deleted old backup: '.$file);
            }
        }

        $this->info('Backup cleanup completed.');
    }
}
