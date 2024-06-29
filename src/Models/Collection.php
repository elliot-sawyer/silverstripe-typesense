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
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ListboxField;
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
    private static $table_name = 'TypesenseCollection';
    private static $db = [
        'Name' => 'Varchar(64)',
        'DefaultSortingField' => 'Varchar(32)',
        'TokenSeperators' => 'Varchar(128)',
        'SymbolsToIndex' => 'Varchar(128)',
        'RecordClass' => 'Varchar(255)',
        'Enabled' => 'Boolean(1)',
        'ImportLimit' => 'Int(10000)',
        'ConnectionTimeout' => 'Int(2)',
        'ExcludedClasses' => 'Text',
        'Sort' => 'Int',
    ];

    private static $has_many = [
        'Fields' => Field::class
    ];

    private static $summary_fields = [
        'Name',
        'ImportLimit',
        'ConnectionTimeout',
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

    private static $defaults = [
        'Enabled' => true,
        'ConnectionTimeout' => 2,
        'ImportLimit' => 10000,
    ];

    private static $cascade_deletes = ['Fields'];

    private static $default_sort = 'Sort ASC';

    public function getCMSFields()
    {
        $recordClassDescription = _t(Collection::class.'.DESCRIPTION_RecordClass', 'The Silverstripe class (and subclasses) of DataObjects contained in this collection.  Only a single object type is supported.  To ensure data consistency it cannot be changed once set; you will need to delete the collection and build a new one');
        $fields = parent::getCMSFields();

        $fields->removeByName(['Name','DefaultSortingField','TokenSeperators','SymbolsToIndex','RecordClass','Enabled', 'ImportLimit', 'ConnectionTimeout', 'ExcludedClasses', 'Sort']);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Name', _t(Collection::class.'.LABEL_Name', 'Name'))
                ->setDescription(_t(Collection::class.'.DESCRIPTION_Name', 'Name of the collection')),

            TextField::create('TokenSeperators', _t(Collection::class.'.LABEL_TokenSeperators', 'Token seperators'))
                ->setDescription(_t(Collection::class.'.DESCRIPTION_TokenSeperators', 'List of symbols or special characters to be used for splitting the text into individual words in addition to space and new-line characters. For e.g. you can add - (hyphen) to this list to make a word like non-stick to be split on hyphen and indexed as two separate words. <a href="https://typesense.org/docs/guide/tips-for-searching-common-types-of-data.html" target="_new">More info</a>')),

            TextField::create('SymbolsToIndex', _t(Collection::class.'.LABEL_SymbolsToIndex', 'Symbols to index'))
                ->setDescription(_t(Collection::class.'.DESCRIPTION_SymbolsToIndex', 'List of symbols or special characters to be indexed. For e.g. you can add + to this list to make the word c++ indexable verbatim. <a href="https://typesense.org/docs/guide/tips-for-searching-common-types-of-data.html" target="_new">More info</a>')),

            DropdownField::create(
                'DefaultSortingField',
                _t(Collection::class.'.LABEL_DefaultSortingField', 'Default sorting field'),
                $this->Fields()
                    ->exclude('type', 'auto')
                    ->map('name', 'name'),
                $this->DefaultSortingField
                )->setHasEmptyDefault(true)
                ->setDescription(_t(Collection::class.'.DESCRIPTION_DefaultSortingField', 'The name of an int32 / float field that determines the order in which the search results are ranked when a sort_by clause is not provided during searching. This field must indicate some kind of popularity. You cannot define a default sort on "auto" fields; it must be an explicitly defined field on your schema')),

            TextField::create('RecordClass', _t(Collection::class.'.LABEL_RecordClass', 'Record class name'))
                ->setDescription($recordClassDescription),
        ]);

        if($this->ID && $this->RecordClass) {
            $excludedClassesList = array_map(function($v) {
                return ClassInfo::shortName($v);
            }, ClassInfo::subclassesFor($this->RecordClass, false));

            $fields->addFieldsToTab('Root.Main', [

                ReadonlyField::create('RecordClass', _t(Collection::class.'.LABEL_RecordClass', 'Record class name'))
                    ->setDescription($recordClassDescription),

                CheckboxField::create('Enabled', _t(Collection::class.'.LABEL_Enabled', 'Enabled'))
                    ->setDescription(_t(Collection::class.'.DESCRIPTION_Enabled', 'When disabled, this collection will not be re-indexed. It is still available through the Typesense client. Do not rely on this for security.')),

                NumericField::create('ImportLimit', _t(Collection::class.'.LABEL_ImportLimit', 'Import limit'))
                    ->setDescription(_t(Collection::class.'.DESCRIPTION_ImportLimit', 'This is the number of documents that can be uploaded into Typesense at once when the sync task is run.  This is usually adjusted for speed and memory reasons, for example if your collection is very large (2M records) or the indexing task is being run on a system with limited memory.')),

                NumericField::create('ConnectionTimeout', _t(Collection::class.'.LABEL_ConnectionTimeout', 'Connection timeout'))
                    ->setDescription(_t(Collection::class.'.DESCRIPTION_ConnectionTimeout', 'When syncing a large dataset to Typesense the connector can time out.  You can adjust this timeout limit as-needed.  The units are measure in seconds.')),

                ListboxField::create('ExcludedClasses', _t(Collection::class.'.LABEL_ExcludedClasses', 'Excluded classes'), $excludedClassesList)
                    ->setDescription(_t(Collection::class.'.DESCRIPTION_ExcludedClasses', "By default, all subclasses of the record class are indexed. To exclude any classes, define an array of them on excludedClasses")),
            ]);
        }
        return $fields;
    }

    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        $typesenseActions = [
            CustomAction::create("syncWithTypesenseServer", _t(Collection::class.'.LABEL_syncWithTypesenseServer', "Update collection in Typesense"))
                ->removeExtraClass('btn-info')
                ->addExtraClass('btn-outline-danger')
                ->setButtonIcon(SilverStripeIcons::ICON_SYNC)
                ->setConfirmation(_t(Collection::class.'.CONFIRM_syncWithTypesenseServer', 'This action will require a reindex, are you sure you want to continue?')),
            CustomAction::create("deleteFromTypesenseServer", _t(Collection::class.'.LABEL_deleteFromTypesenseServer', "Delete from Typesense"))
                ->removeExtraClass('btn-info')
                ->addExtraClass('btn-outline-danger')
                ->setButtonIcon(SilverStripeIcons::ICON_TRASH_BIN)
                ->setConfirmation(_t(Collection::class.'.CONFIRM_deleteFromTypesenseServer','You are about to delete your collection, are you sure?'))
        ];

        $groupAction = ActionButtonsGroup::create($typesenseActions);

        if($this->ID && $this->Fields()->Count() > 0) {
            $actions->push($groupAction);
        }


        return $actions;
    }

    public function syncWithTypesenseServer(): string
    {
        $this->__createOrUpdateOnServer();
        return _t(Collection::class.'.MESSAGE_syncWithTypesenseServer', 'Synchronization of {name} with Typesense completed', ['name' => $this->Name]);
    }

    public function deleteFromTypesenseServer(): string
    {
        $this->__deleteOnServer();
        return _t(Collection::class.'.MESSAGE_deleteFromTypesenseServer', 'Collection {name} on Typesense server completed', ['name', ($this->ID ? $this->Name : '')]);
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
        $collection->ConnectionTimeout = $collectionFields['connection_timeout'] ?? 2;

        $excludedClasses = $collectionFields['excluded_classes'] ?? [];
        $collection->ExcludedClasses = mb_strtolower(json_encode($excludedClasses));
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
    public function FieldsArray() : array
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
            'Name', 'RecordClass'
        ]));

        return $validator;
    }

    public function validate()
    {
        $valid = parent::validate();
        if(!class_exists($this->RecordClass)) {
            $valid->addFieldError('RecordClass', _t(Collection::class.'.FIELDERROR_RecordClass', 'Invalid class'));
        }
        return $valid;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $importLimit = (int) $this->ImportLimit ?? $this->config()->defaults['ImportLimit'];
        $connectionTimeout = (int) $this->ConnectionTimeout ?? $this->config()->defaults['ConnectionTimeout'];
        if($importLimit <= 0) {
            $importLimit = 1;
        }
        if($connectionTimeout <= 0) {
            $connectionTimeout = 1;
        }

        $this->ImportLimit = $importLimit;
        $this->ConnectionTimeout = $connectionTimeout;
    }

    /**
     * Bulk load documents into Typesense
     *
     * @return void
     */
    public function import(): void
    {
        $limit = (int) ($this->ImportLimit ?: 10000);
        $connection_timeout = (int) $this->ConnectionTimeout ?: 2;
        $client = Typesense::client($connection_timeout);
        $i = 0;
        $count = $this->getRecordsCount();
        DB::alteration_message(
            _t(Collection::class.'.IMPORT_Indexing', "Indexing {name}, (Limit: {limit}, Timeout: {timeout}", ['name' => $this->Name, 'limit' => $limit, 'timeout' => $connection_timeout])
        );
        if($count === 0) {
            DB::alteration_message(
                '...'
                ._t(Collection::class.'.IMPORT_NoDocumentsFound', 'no documents found!')
            );
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
            DB::alteration_message(
                '...'
                ._t(Collection::class.'.IMPORT_AddedDocumentsToCollection', 'added [{limitcount} / {count}] documents to {name}', ['limitcount' => $i + $limitCount, 'count' => $count, 'name' => $this->Name])
            );

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

        if($this->ExcludedClasses) {
            $excludedClasses = json_decode($this->ExcludedClasses, true);
            if($excludedClasses) {
                $records = $records->exclude('ClassName', array_values($excludedClasses));
            }
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

    public function checkExistance()
    {
        $client = Typesense::client();
        return $client->collections[$this->Name]->exists();
    }
}
