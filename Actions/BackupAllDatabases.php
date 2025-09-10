<?php

namespace App\Vito\Plugins\MaMe\AutoDatabaseBackup\Actions;

use App\Actions\Database\ManageBackup;
use App\Actions\Database\RunBackup;
use App\Enums\DatabaseStatus;
use App\Models\Backup;
use App\Models\StorageProvider;
use App\ServerFeatures\Action;
use Illuminate\Http\Request;

class BackupAllDatabases extends Action
{
    public function name(): string
    {
        return 'backup-all';
    }

    public function active(): bool
    {
        return $this->server->databases()->where('status', DatabaseStatus::READY)->exists();
    }

    public function handle(Request $request): void
    {
        $databases = $this->server->databases()->where('status', DatabaseStatus::READY)->get();
        if ($databases->isEmpty()) {
            return;
        }

        $storage = StorageProvider::getByProjectId($this->server->project_id)->first();

        foreach ($databases as $database) {
            /** @var Backup|null $existing */
            $existing = $database->backups()->orderByDesc('id')->first();

            if ($existing) {
                (new RunBackup())->run($existing);
                continue;
            }

            if (! $storage) {
                // Nessuno storage disponibile: non possiamo creare un backup per questo DB
                continue;
            }

            // Crea una configurazione di backup e lo esegue immediatamente
            (new ManageBackup())->create($this->server, [
                'storage' => $storage->id,
                'keep' => 7,
                'interval' => '0 0 * * *',
                'database' => $database->id,
            ]);
        }
    }
}
