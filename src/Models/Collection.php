<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use Exception;
use LeKoala\CmsActions\ActionButtonsGroup;
use LeKoala\CmsActions\CustomAction;
use LeKoala\CmsActions\SilverStripeIcons;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBTime;
use Typesense\Exceptions\ObjectAlreadyExists;

class Collection extends DataObject
{
    private static $import_connection_timeout = 300;
    private static $table_name = 'TypesenseCollection';
    private static $db = [
        'Name' => 'Varchar(64)',
        'DefaultSortingField' => 'Varchar(32)',
        'TokenSeperators' => 'Varchar(128)',
        'SymbolsToIndex' => 'Varchar(128)',
        'RecordClass' => 'Varchar(255)',
        'Enabled' => 'Boolean(1)',
        'ImportLimit' => 'Int(10000)'
    ];

    private static $has_many = [
        'Fields' => Field::class
    ];

    private static $summary_fields = [
        'Name',
        'DefaultSortingField',
        'TokenSeperators',
        'SymbolsToIndex',
        'RecordClass',
        'Enabled.Nice' => 'Is enabled',
    ];

    private static $default_collection_fields = [
        ['name' => 'id', 'type' => 'int64'],
        ['name' => 'ClassName', 'type' => 'string'],
        ['name' => 'LastEdited', 'type' => 'int64'],
        ['name' => 'Created', 'type' => 'int64'],
    ];

    private static $cascade_deletes = ['Fields'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Name','DefaultSortingField','TokenSeperators','SymbolsToIndex','RecordClass','Enabled', 'ImportLimit']);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Name')
                ->setDescription('Name of the collection'),

            TextField::create('TokenSeperators')
                ->setDescription('List of symbols or special characters to be used for splitting the text into individual words in addition to space and new-line characters. For e.g. you can add - (hyphen) to this list to make a word like non-stick to be split on hyphen and indexed as two separate words. <a href="https://typesense.org/docs/guide/tips-for-searching-common-types-of-data.html" target="_new">More info</a>'),

            TextField::create('SymbolsToIndex')
                ->setDescription('List of symbols or special characters to be indexed. For e.g. you can add + to this list to make the word c++ indexable verbatim. <a href="https://typesense.org/docs/guide/tips-for-searching-common-types-of-data.html" target="_new">More info</a>'),

            DropdownField::create(
                'DefaultSortingField',
                'Default sorting field',
                $this->Fields()->map('name', 'name'),
                $this->DefaultSortingField
            )->setHasEmptyDefault(true)
                ->setDescription('The name of an int32 / float field that determines the order in which the search results are ranked when a sort_by clause is not provided during searching. This field must indicate some kind of popularity. '),
            ReadonlyField::create('RecordClass', 'Record class name')
                ->setDescription('The Silverstripe class (and subclasses) of DataObjects contained in this collection.  TODO: Multiple objects are not yet supported'),
            CheckboxField::create('Enabled')
                ->setDescription('When disabled, this collection will not be re-indexed. It is still available through the Typesense client. Do not rely on this for security.'),

            NumericField::create('ImportLimit')
                ->setDescription('This is the number of documents that can be uploaded into Typesense at once when the sync task is run.  This is usually adjusted for speed and memory reasons, for example if your collection is very large (2M records) or the indexing task is being run on a system with limited memory.')
        ]);
        return $fields;
    }

    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        $typesenseActions = [
            CustomAction::create("syncWithTypesenseServer", "Update collection in Typesense")
                ->setAttribute('title', 'This action will require a reindex')
                ->removeExtraClass('btn-info')
                ->addExtraClass('btn-outline-danger')
                ->setButtonIcon(SilverStripeIcons::ICON_SYNC)
                ->setConfirmation('This action will require a reindex, are you sure you want to continue?'),
            CustomAction::create("deleteFromTypesenseServer", "Delete from Typesense")
                ->removeExtraClass('btn-info')
                ->addExtraClass('btn-outline-danger')
                ->setButtonIcon(SilverStripeIcons::ICON_TRASH_BIN)
                ->setConfirmation('You are about to delete your collection, are you sure?')
        ];

        $groupAction = ActionButtonsGroup::create($typesenseActions);

        if($this->ID) {
            $actions->push($groupAction);
        }


        return $actions;
    }

    public function syncWithTypesenseServer(): string
    {
        $this->__createOrUpdateOnServer();
        return 'Synchronization of '.$this->Name.' with Typesense completed';
    }

    public function deleteFromTypesenseServer(): string
    {
        $this->__deleteOnServer();
        return 'Collection' .($this->ID ? $this->Name : ''). ' on Typesense server completed';
    }

    public function onAfterBuild()
    {
        $copyright = (new Typesense())->CopyrightStatement();
        DB::alteration_message($copyright);
    }

    /**
     * Find an existing Typesense collection or make a new one
     *
     * @param [type] $name
     * @param [type] $recordClass
     * @param [type] $collectionFields
     * @return Collection
     */
    public static function find_or_make($name, $recordClass, $collectionFields): Collection
    {
        $collection = Collection::get()->find('Name', $name)
            ?: Collection::create(['Name' => $name]);

        if($recordClass && class_exists($recordClass) && !$collection->RecordClass) {
            $collection->RecordClass = $recordClass;
        }
        $collection->DefaultSortingField = $collectionFields['default_sorting_field'] ?? null;
        $collection->TokenSeperators = $collectionFields['token_separators'] ?? null;
        $collection->SymbolsToIndex = $collectionFields['symbols_to_index'] ?? null;
        $collection->ImportLimit = $collectionFields['import_limit'] ?? 10000;
        $collection->write();
        foreach($collectionFields['fields'] as $fieldDefinition) {
            $field = Field::find_or_make($fieldDefinition, $collection->ID);
            $collection->Fields()->add($field);
        }

        return $collection;
    }

    /**
     * Collect all fields from the database relationship into a single array for Typesense to understand
     * Omits usual dataobject fields injected by Silverstripe
     *
     * @return array
     */
    protected function FieldsArray() : array
    {
        $arr = [];
        foreach($this->Fields() as $field) {
            $arr[] = [
                'name' => $field->name ?? '.*',
                'type' => $field->type ?? 'auto',
                'facet' => (bool) $field->facet,
                'optional' => (bool) $field->optional,
                'index' => (bool) $field->index,
                'sort' => (bool) $field->sort,
                'store' => (bool) $field->store,
                'infix' => (bool) $field->infix,
            ];
        }
        foreach($this->config()->default_collection_fields as $field) {
            $arr[] = $field;
        }

        return $arr;
    }


    /**
     * Update or add this collection on the remote server
     *
     * */
    private function __createOrUpdateOnServer(): void
    {
        $client = Typesense::client();
        try {
            $schema = [
                'name' => $this->Name,
                'enable_nested_fields' => true,
                'fields' => $this->FieldsArray()
            ];

            if($this->DefaultSortingField) { $schema['default_sorting_field'] = $this->DefaultSortingField; }
            if($this->TokenSeperators) { $schema['token_separators'] = $this->TokenSeperators; }
            if($this->SymbolsToIndex) { $schema['symbols_to_index'] = $this->SymbolsToIndex; }

            //poor man's update: delete the collection first
            //then recreate it.  Requires a reindex
            //TODO: itemized schemas
            if($client->collections[$this->Name]->exists()) {
                $client->collections[$this->Name]->delete();
            }

            //then create it
            $client->collections->create($schema);


        } catch (ObjectAlreadyExists $e) {
            Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
        }
    }

    /**
     * Delete this collection from the remote server
     *
     * @return void
     */
    private function __deleteOnServer()
    {
        $client = Typesense::client();
        try {
            $client->collections[$this->Name]->delete();
        } catch(Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
        }
    }

    public function onAfterDelete()
    {
        $this->deleteFromTypesenseServer();
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();

        $validator->addValidator(RequiredFields::create([
            'Name',
        ]));

        return $validator;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $importLimit = (int) $this->ImportLimit;
        if($importLimit < 0) {
            $importLimit = 1;
        }

        $this->ImportLimit = $importLimit;
    }

    /**
     * Bulk load documents into Typesense
     *
     * @param integer $limit
     * @param integer $connection_timeout
     * @return void
     */
    public function import()
    {
        $limit = (int) ($this->ImportLimit ?: 1);
        $connection_timeout = static::config()->import_connection_timeout;
        $client = Typesense::client($connection_timeout);
        $i = 0;
        $count = $this->getRecordsCount();
        DB::alteration_message(sprintf("Indexing %s", $this->Name));
        if($count === 0) {
            DB::alteration_message('... no documents found!');
        }
        while($records = $this->getRecords()->limit($limit, $i)) {
            $limitCount = $records->count();
            if($limitCount == 0) break;
            $docs = [];

            $fieldsArray = $this->FieldsArray();
            foreach($records as $record) {
                $data = [];
                if($record->hasMethod('getTypesenseDocument')) {
                    $data = $record->getTypesenseDocument();
                } else {
                    $data = $this->getTypesenseDocument($record, $fieldsArray);
                }
                if($data) {
                    $docs[] = $data;
                }
            }
            $client->collections[$this->Name]->documents->import($docs, ['action' => 'emplace']);
            DB::alteration_message(sprintf("... added [%d / %d] documents to %s", $i + $limitCount, $count, $this->Name));

            $i += $limit;
        }
    }

    /**
     * Get all records for this versions RecordClass
     *
     * @return DataList
     */
    protected function getRecords(): DataList
    {
        $records = null;
        $recordClass = $this->RecordClass;
        if(class_exists('SilverStripe\Subsites\Model\Subsite')) {
            \SilverStripe\Subsites\Model\Subsite::disable_subsite_filter();
        }
        if(class_exists('SilverStripe\Versioned\Versioned')) {
            $records = \SilverStripe\Versioned\Versioned::get_by_stage($recordClass, \SilverStripe\Versioned\Versioned::LIVE);
        } else {
            $records = $recordClass::get();
        }

        return $records;
    }

    /**
     * Get the total database count of available documents that can be added to this collection
     * Note: This is NOT the total documents that have been added to Typesense,
     *
     * @return integer
     */
    protected function getRecordsCount(): int
    {
        return $this->getRecords()->count();
    }

    /**
     * Converts a Silverstripe record into a Typesense document according to its schema
     *
     * @param DataObject $record
     * @param array $fieldsArray array of fields defined on the collection
     * @return array
     */
    public function getTypesenseDocument($record, $fieldsArray = []): array
    {
        $data = [];
        foreach($fieldsArray as $field) {
            $name = $field['name'];
            $data[$name] = $record->__get($name);
            if(!$data[$name] && $record->hasMethod($name)) {
                $data[$name] = $record->$name();
            }
            if(strtolower($name) == 'id') {
                $data['id'] = (string) $record->ID;
            }
            if($record->dbObject($name) instanceof DBDate || $record->dbObject($name) instanceof DBTime) {
                $data[$name] = strtotime($record->$name);
            }
        }

        return $data;
    }
}
