<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use SilverStripe\Dev\BuildTask;

class TypesenseSyncTask extends BuildTask
{
    public $title = 'Typesense sync task';
    public $description = "Creates your typesense collections, and ... (TODO: does the actual indexing)";
    private static $segment = 'TypesenseSyncTask';
    public function run($request = null)
    {
        $client = Typesense::client();
        $collections = $this->findOrMakeAllCollections();
        if(!$collections) return;
    }

    private function findOrMakeAllCollections()
    {
        $ymlIndexes = Typesense::config()->get('collections');
        foreach($ymlIndexes as $recordClass => $collection) {
            $collectionName = $collection['name'] ?? null;
            if(!$collectionName) continue;

            $dbCollection = Collection::find_or_make($collectionName, $recordClass, $collection);

        }
        $indexes = Collection::get();
        return $indexes;
    }
}