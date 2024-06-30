## Syncing and managing documents in Typesense

All "documents" in Typesense are simply JSON objects you have sent to it; for example, Pages in a Silverstripe installation or a collection of DataObjects.  This module provides ways to provide this data to Typesense.

`ElliotSawyer\SilverstripeTypesense\Collection::getTypesenseDocument` is a generic method to convert a Silverstripe record into a Typesense document according to its schema.  It is intended as a one-size-fits-all solution, but sometimes you need to customize data the data that's provided in a field (for example, if you're using something like Tika to extract text from a document).  If you need to do this, you'll need to define a method on your record class:

```php
    public function getTypesenseDocument()
    {
        return [
            'id' => (string) $this->ID,
            'Field1' => $this->Field1,
            'Field2' => SomeOtherModule::inst()->doSomeStuff(),
            'Field3' => $this->SomeGetterMethod(),
            'Field4' => $this->SomeRelationship()->map()->toArray(),
            'Tags' => $this->Tags()->map('Name')->toArray(),
            'ClassName' => $this->ClassName,
            'Created' => strtotime($this->Created),
            'LastEdited' => strtotime($this->LastEdited),
        ]
    }
```

The ID can be any unique value, but it _must_ be cast into a string or Typesense will reject it. `ClassName`, `Created`, `LastEdited` are included for use within Silverstripe (such as within ModelAdmin and Gridfields) and _may_ be optional (untested).  However, if you do insert timestamps because you want to search for or sort with them, they must be converted into integer values for Typesense to accept them.

### Bulk import

The module includes a BuildTask called `TypesenseSyncTask`.  Once the collection has been uploaded to Typesense, the TypesenseSyncTask will scan the collection's RecordClass records and bulk-import all of the documents it has been configured to upload. New records are inserted into Typesense according to their `id` field, and existing ones are updated with the current database content.

### One-off saves or deletions

The module is configured to automatically update the collection when an object is saved or deleted (or in the case of versioned objects, publish and unpublished).

This can potentially make imports and programmatically generated data slower.  If this is an issue, you can delete the collection before importing or running your build script, then re-install it afterwards and do a bulk-import.

