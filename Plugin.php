<?php

namespace App\Vito\Plugins\MaMe\AutoDatabaseBackup;

use App\Actions\Database\ManageBackup;
use App\Models\Database;
use App\Models\StorageProvider;
use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterServerFeature;
use App\Plugins\RegisterServerFeatureAction;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Auto Database Backup';

    protected string $description = 'Crea automaticamente un backup quando si crea un database e aggiunge un\'azione per eseguire il backup di tutti i database su un server.';

    public function boot(): void
    {
        // Registra una feature di server con un\'azione per fare il backup di tutti i database
        RegisterServerFeature::make('database-automation')
            ->label('Database automation')
            ->description('Azioni automatiche e di massa per i database')
            ->register();

        RegisterServerFeatureAction::make('database-automation', 'backup-all')
            ->label('Backup di tutti i database')
            ->handler(\App\Vito\Plugins\MaMe\AutoDatabaseBackup\Actions\BackupAllDatabases::class)
            ->register();

    // Auto-backup alla creazione di un database
    \Illuminate\Support\Facades\Event::listen('eloquent.created: '.Database::class, function (Database $database): void {
            // Evita duplicati: se esiste già un backup per questo database non crearne un altro automaticamente
            if ($database->backups()->exists()) {
                return;
            }

            $server = $database->server;

            // Scegli uno storage provider disponibile per il progetto del server (o globale)
            $storage = StorageProvider::getByProjectId($server->project_id)->first();
            if (! $storage) {
                // Nessuno storage configurato: esci silenziosamente
                return;
            }

            // Crea un job di backup pianificato (e parte immediatamente via ManageBackup::create)
            (new ManageBackup())->create($server, [
                'storage' => $storage->id,
                'keep' => 7,
                'interval' => '0 0 * * *', // Daily
                'database' => $database->id,
            ]);
        });
    }
}
