<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class Collection extends DataObject
{
    private static $table_name = 'TypesenseCollection';
    private static $db = [
        'Name' => 'Varchar(64)',
        'DefaultSortingField' => 'Varchar(32)',
        'RecordClass' => 'Varchar(255)',
        'Enabled' => 'Boolean(1)',
    ];

    private static $has_many = [
        'Fields' => Field::class
    ];

    private static $summary_fields = [
        'Name'
    ];

    private static $default_collection_fields = [
        ['name' => 'id', 'type' => 'int64'],
        ['name' => 'ClassName', 'type' => 'string'],
        ['name' => 'LastEdited', 'type' => 'int64'],
        ['name' => 'Created', 'type' => 'int64'],
    ];

    private static $cascade_deletes = ['Fields'];

    public function onAfterBuild()
    {
        $copyright = (new TypesenseController())->CopyrightStatement();
        DB::alteration_message($copyright);
    }
}
