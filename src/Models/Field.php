<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class Field extends DataObject {
    private static $table_name = 'TypesenseField';

    // https://typesense.org/docs/26.0/api/collections.html#field-types
    private static $field_types = [
        'string',            //"String values",
        'string[]',         //"Array of strings",
        'int32',             //"Integer values up to 2,147,483,647",
        'int32[]',          //"Array of int32",
        'int64',             //"Integer values larger than 2,147,483,647",
        'int64[]',          //"Array of int64",
        'float',             //"Floating point / decimal numbers",
        'float[]',          //"Array of floating point / decimal numbers",
        'bool',              //"true or false",
        'bool[]',           //"Array of booleans",
        'geopoint',          //"Latitude and longitude specified as [lat, lng]. Read more here.",
        'geopoint[]',       //"Arrays of Latitude and longitude specified as [[lat1, lng1], [lat2, lng2]]. Read more here.",
        'object',            //"Nested objects. Read more here.",
        'object[]',         //"Arrays of nested objects. Read more here.",
        'string*',          //"Special type that automatically converts values to a string or string[].",
        'image',             //"Special type that is used to indicate a base64 encoded string of an image used for Image search.",
        'auto',              //"Special type that automatically attempts to infer the data type based on the documents added to the collection. See automatic schema detection.",
    ];

    /**
     * These are deliberately lowercased by typesense conventions
     *
     * @var array
     */
    private static $db = [
        'name' => 'Varchar(255)',
        'type' => 'Varchar(10)',
        'facet' => 'Boolean(0)',
        'optional' => 'Boolean(0)',
        'index' => 'Boolean(1)',
        'sort' => 'Boolean(1)',
        'store' => 'Boolean(1)',
        'infix' => 'Boolean(0)',

        //todo: these are advanced features to be enabled later
        // 'locale' => 'Varchar(2)',
        // 'num_dim' => 'Decimal(10,8)',
        // 'vec_dist' => 'Enum("cosine,ip","cosine")',
        // 'reference' => 'Varchar(255)',
        // 'range_index' => 'Boolean(0)',
        'stem' => 'Boolean(0)',
    ];

    private static $has_one = [
        'Collection' => Collection::class
    ];

    private static $summary_fields = [
        'name' => 'Name',
        'type' => 'Type',
        'facet.Nice' => 'Facet',
        'index.Nice' => 'Index',
        'store.Nice' => 'Store',
        'optional.Nice' => 'Optional',
        'sort.Nice' => 'Sort',
        'infix.Nice' => 'Infix',
        'stem.Nice' => 'Stemming',
    ];

    private static $defaults = [
        'facet' => false,
        'optional' => false,
        'index' => true,
        'sort' => true,
        'store' => true,
        'infix' => false,
        'stem' => 0,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['CollectionID','name', 'type','facet','optional','index','sort','store','infix','stem']);
        $types = array_combine(
            $this->config()->field_types,
            $this->config()->field_types
        );
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('name', _t(Field::class.'.LABEL_name', 'Name'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_name', "Name of the field.")),
            DropdownField::create('type', _t(Field::class.'.LABEL_type', 'Type'), $types)
                ->setDescription(_t(Field::class.'.DESCRIPTION_type', "The data type of the field. An explanation for each field is <a href='https://typesense.org/docs/26.0/api/collections.html#field-types' target='_new'>here</a>")),
            CheckboxField::create('facet', _t(Field::class.'.LABEL_facet', 'Facet'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_facet', "Enables faceting on the field. Default: false.")),
            CheckboxField::create('optional', _t(Field::class.'.LABEL_optional', 'Optional'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_optional', "When set to true, the field can have empty, null or missing values. Default: false.")),
            CheckboxField::create('index', _t(Field::class.'.LABEL_index', 'Index'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_index', "When set to false, the field will not be indexed in any in-memory index (e.g. search/sort/filter/facet). Default: true.")),
            CheckboxField::create('sort', _t(Field::class.'.LABEL_sort', 'Sort'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_sort', "When set to true, the field will be sortable. 'auto' fields cannot be sorted. Default: true for numbers, false otherwise.")),
            CheckboxField::create('store', _t(Field::class.'.LABEL_store', 'Store'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_store', "When set to false, the field value will not be stored on disk.  Default: true.")),
            CheckboxField::create('infix', _t(Field::class.'.LABEL_infix', 'Infix'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_infix', "When set to true, the field value can be infix-searched. Incurs significant memory overhead. Default: false.")),
            CheckboxField::create('stem', _t(Field::class.'.LABEL_stem', 'Stem'))
                ->setDescription(_t(Field::class.'.DESCRIPTION_stem', "Stemming allows you to handle common word variations (singular / plurals, tense changes) of the same root word. For example: searching for walking, will also return results with walk, walked, walks, etc when stemming is enabled. This feature uses <a href='https://snowballstem.org/'>Snowball Stemmer</a>: language selection for stemmer is automatically made from the value of the locale property associated with the field (only 'en' is tested but other languages may work). Default: false")),
        ]);
        return $fields;
    }

    public function validate()
    {
        $valid = parent::validate();
        if(!in_array($this->type, $this->config()->field_types)) {
            $valid->addFieldError('type', _t(Field::class.'.FIELDERROR_type', 'Invalid field type'));
        }

        if($this->type == 'string[]' && $this->sort == true) {
            $valid->addFieldError('sort', _t(Field::class.'.FIELDERROR_sort', 'Sorting on string[] and string* is not supported'));
        }
        return $valid;
    }

    public static function find_or_make($fieldDefinition, $parentID): Field
    {
        $field = Field::get()->filter($fieldDefinition + ['CollectionID' => $parentID])->first()
            ?: Field::create($fieldDefinition + ['CollectionID' => $parentID]);
        if(!$field?->ID) {
            $field->write();
        }

        return $field;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->type == 'auto') {
            $this->sort = false;
        }
        if($this->facet == true) {
            $this->optional = true;
        }
        if($this->type == 'string*' || $this->type == 'string[]' ) {
            $this->sort = false;
        }
    }
}
