<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class TypesenseSyncTask extends BuildTask
{
    private static $segment = 'TypesenseSyncTask';
    public function getTitle()
    {
        return _t(TypesenseSyncTask::class.'.TITLE', 'Typesense sync task');
    }

    public function getDescription()
    {
        return _t(TypesenseSyncTask::class.'.DESCRIPTION', 'Creates and indexes your Typesense collections');
    }
    public function run($request = null)
    {
        $copyright = (new Typesense())->CopyrightStatement();
        DB::alteration_message($copyright);
        $client = Typesense::client();
        $this->extend('onBeforeBuildAllCollections');
        $collections = $this->findOrMakeAllCollections();
        $this->extend('onAfterBuildAllCollections', $collections);
        if(!$collections) return;

        $this->extend('onBeforeImportDocuments');
        foreach($collections as $collection) {
            if(!$client->collections[$collection->Name]->exists()) {
                DB::alteration_message($collection->syncWithTypesenseServer());
            }
            $collection->import();
        }
        $this->extend('onAfterImportDocuments', $collections);
        $this->extend('onEndOfSyncTask');
    }

    private function findOrMakeAllCollections()
    {
        $ymlIndexes = Typesense::config()->get('collections');
        foreach($ymlIndexes as $recordClass => $collection) {
            $collectionName = $collection['name'] ?? null;
            if(!$collectionName) continue;

            $dbCollection = Collection::find_or_make($collectionName, $recordClass, $collection);

        }
        $indexes = Collection::get()
            ->sort('Sort ASC')
            ->filter([
                'Enabled' => true
            ]);
        return $indexes;
    }
}
