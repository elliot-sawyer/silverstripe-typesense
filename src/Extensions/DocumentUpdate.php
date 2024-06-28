<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\RequestMalformed;

class DocumentUpdate extends DataExtension
{
    private function getValidTypesenseClasses()
    {
        $cache = Injector::inst()->get(CacheInterface::class.'.TypesenseCache');
        if(!($classes = $cache->get('Classes'))) {
            $classes = Collection::get()->column('RecordClass');
            $cache->set('Classes', $classes, 86400);
        }
        return $classes;
    }
    private function getTypesenseCollection()
    {
        return Collection::get()
            ->find('RecordClass', $this->owner->ClassName);
    }
    public function onAfterWrite()
    {
        try {
            if(in_array($this->owner, $this->getValidTypesenseClasses())) {
                $client = Typesense::client();
                $collection = $this->getTypesenseCollection();
                if($collection) {
                    $record = $this->owner;
                    $data = [];
                    if(method_exists($record, 'getTypesenseDocument')) {
                        $data = $record->getTypesenseDocument();
                    } else {
                        $data = $collection->getTypesenseDocument($record, $collection->FieldsArray());
                    }
                    $client->collections[$collection->Name]->documents->upsert($data);
                }
            }
        } catch (RequestMalformed $e) {
            Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
        }
    }

    public function onBeforeDelete()
    {
        try {
            if(in_array($this->owner, $this->getValidTypesenseClasses())) {
                $client = Typesense::client();
                $collection = $this->getTypesenseCollection();
                if($collection && $this->owner->ID) {
                    $client->collections[$collection->Name]->documents[(string) $this->owner->ID]->delete();
                }
            }
        } catch (ObjectNotFound $e) {
            Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
        }
    }
}
