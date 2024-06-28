# silverstripe-typesense

New home for the Silverstripe Typesense project

## Installation

Add this to your composer.json:

```json
    "repositories": {
        "elliot-sawyer/silverstripe-typesense": {
            "type": "vcs",
            "url": "git@codeberg.org:0x/silverstripe-typesense.git"
        }
    },
```

Then run `composer require elliot-sawyer/silverstripe-typesense`.

The repositories step is not necessary once the module is public.

## Configuration

You need a few environment variables defined:
```
TYPESENSE_API_KEY="......"
TYPESENSE_SERVER="......"
```

* TYPESENSE_API_KEY can be any random text, but must be kept secret. Anybody with this key has full administrator control over your Typesense installation.  It can be generated with `head /dev/urandom | shasum -a 256`
* TYPESENSE_SERVER is an _externally facing_ hostname: it may work internally, but users will be sending requests directly to it so it must be accessible in a browser.  For example `http://localhost:8108` may work for you, but if your environment is not publicly listening on 8108 the request may fail.

You can, of course, use an externally hosted Typesense server using a service such as [Typesense Cloud](https://cloud.typesense.org).

## Development Environment

The author uses docker-compose for development but this is not required.

```yml
version: "3"

services:
  webserver:
    image: php:8.3
    ...
  database:
    image: mysql:8
    ...
  typesense:
    image: typesense/typesense:26.0
    restart: on-failure
    ports:
      - "28108:8108"
    volumes:
      - ./typesense-data:/data
    command: '--data-dir /data --api-key=${TYPESENSE_API_KEY} --enable-cors'
    environment:
      TYPESENSE_API_KEY: ${TYPESENSE_API_KEY}
  typesense-dashboard:
    image: ghcr.io/bfritscher/typesense-dashboard:latest
    ports:
      - '${HOST_MACHINE_TYPESENSE_DASHBOARD_PORT}:80'

```

The two local Typesense images are not actually necessary: you can develop against an externally hosted Typesense installation.  

The dashboard application is available publicly at https://bfritscher.github.io/typesense-dashboard. It is an "offline" application in that it can be saved offline and run inside a private network without an external internet connection.  It can also be used to connect to any Typesense installation in the world, as long as that installation has CORS enabled and the domain has been whitelisted.


## Creating a collection

Collections are defined and initially created with YML:

```yml
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    Page:
      name: Pages
      fields:
        - { name: Title, type: string, sort: true }
        - { name: Content, type: string, optional: true }
        - { name: Link, type: string, index: false, optional: true}
      default_sorting_field: # 'exampleField'
      token_separators: # '-'
      symbols_to_index: # '+'
      import_limit: 10000
      connection_timeout: 2 
      excludedClasses:
        - SilverStripe\ErrorPage\ErrorPage
```

When the task `TypesenseSyncTask` is run, all collections defined in this YML will be built.  you will need to visit the collection in the CMS first and upload it into Typesense.  Once this is done, the next time you run the task records will be bulk imported.

### Configuring your collection

The only fields that are required here are `name` and the `fields` array. `excludedClasses` is used when you want to exclude a particular subclass from being indexed (for example, error pages, redirectors, and other pages that may not have any searchable content).

These fields are also optional.  They affect how the collection indexes content and the order of the results.  More information of these fields can be found [here](https://typesense.org/docs/26.0/api/collections.html#schema-parameters).

#### default_sorting_field: 

This must be a `int32` or `float` field that exists inside your collection. To work properly it should denote some kind of popularity or score.

#### token_separators

List of symbols or special characters to be used for splitting the text into individual words in addition to space and new-line characters.

For example you can add - (hyphen) to this list to make a word like non-stick to be split on hyphen and indexed as two separate words.

#### symbols_to_index

List of symbols or special characters to be indexed.

For example you can add + to this list to make the word c++ indexable verbatim.

#### import_limit and connection_timeout

These used internally by the Silverstripe Typesense module to limit how many records can be imported into Typesense at once. You would only need to change these when considering performance or resourcing issues.  For example, you may have a large amount of RAM and processing power available, you might consider increasing the **import_limit** value to handle larger bulk uploads. However, local resources are not everything: the Typesense server may not be able handle a large number of documents responsively (for example if it is underload).  In this case, you might consider increasing the **connection_timeout** value

### Configuring your fields

Fields should correspond with individual fields on your DataObject, but a field can actually represent anything - such as a getter method or an array of results from a relationship - depending on the data type of the field.

#### Field attributes 

Each field can have the following attributes.  Only `name` and `type` are required fields

* **name**: Name of the field.
* **type**: The data type of the field (see the section below for a list of types).
* **facet**: Enables faceting on the field. Default: false.
* **optional**: When set to true, the field can have empty, null or missing values. Default: false.
* **index**: When set to false, the field will not be indexed in any in-memory index (e.g. search/sort/filter/facet). Default: true.
* **store**: When set to false, the field value will not be stored on disk. Default: true.
* **sort**: When set to true, the field will be sortable. Default: true for numbers, false otherwise.
* **infix**: When set to true, the field value can be infix-searched. Incurs significant memory overhead. Default: false.

#### Field types

* **string**: String values
* **string[]**: Array of strings
* **int32**: Integer values up to 2,147,483,647
* **int32[]**: Array of int32
* **int64**: Integer values larger than 2,147,483,647
* **int64[]**: Array of int64
* **float**: Floating point / decimal numbers
* **float[]**: Array of floating point / decimal numbers
* **bool**: true or false
* **bool[]**: Array of booleans
* **geopoint**: Latitude and longitude specified as [lat, lng].
* **geopoint[]**: Arrays of Latitude and longitude specified as [[lat1, lng1], [lat2, lng2]].
* **object**: Nested objects.
* **object[]**: Arrays of nested objects.
* **string***: Special type that automatically converts values to a string or string[].
* **image**: Special type that is used to indicate a base64 encoded string of an image used for Image search.
* **auto**: Special type that automatically attempts to infer the data type based on the documents added to the collection.
#

### Managing your collections

Collections can also be created, managed, and deleted in the CMS. What's in the database is considered the "source of truth". 

Once the collection is created in the CMS and at least one field is added, you'll have two CMS actions available.  These are potentially destructive actions because they change the data present on the remote Typesense server.

#### Update collection in Typesense

This is used to update the collection when the schema has changed. This is achieved by replacing it entirely if it already exists. When this occurs, the collection will need to be reindexed with `TypesenseSyncTask`.  

Bulk imports are not possible until the collection has been uploaded to Typesense.

#### Delete from Typesense

This is used to delete the collection from Typesense.  When the local collection is deleted, this action is invoked to automatically remove it from Typesense.

### Managing documents in Typesense

All "documents" in Typesense are simply JSON objects you have sent to it; for example, Pages in a Silverstripe installation or a collection of DataObjects.  This module provides ways to provide this data to Typesense.

`ElliotSawyer\SilverstripeTypesense\Collection::getTypesenseDocument` is a generic method to convert a Silverstripe record into a Typesense document according to its schema.  It is intended as a one-size-fits-all solution, but sometimes you need to customize data the data that's provided in a field (for example, if you're using something like Tika to extract text from a document).  If you need to do this, you'll need to define a method on your record class:

```php
    public function getTypesenseDocument()
    {
        return [
            'id' => (string) $this->ID,
            'Field1' => '...',
            'Field2' => '...',
            'Field3' => '...',
            'ClassName' => $this->ClassName,
            'Created' => strtotime($this->Created),
            'LastEdited' => strtotime($this->LastEdited),
        ]
    }
```

The ID can be any unique value, but it _must_ be cast into a string or Typesense will reject it. `ClassName`, `Created`, `LastEdited` are included for use within Silverstripe (such as within ModelAdmin and Gridfields) and _may_ be optional (untested).  However, if you do insert timestamps because you want to search for or sort with them, they must be converted into integer values for Typesense to accept them.

#### Bulk import

The module includes a BuildTask called `TypesenseSyncTask`.  Once the collection has been uploaded to Typesense, the TypesenseSyncTask will bulk-import all of the documents it has been configured to upload. New records are inserted into Typesense according to their `id` field

#### One-off saves or deletions

The module is configured to automatically update the collection when an object is saved or deleted (or in the case of versioned objects, publish and unpublished).

This can potentially make imports and programmatically generated data slower.  If this is an issue, you can delete the collection before importing or running your build script, then re-install it afterwards and do a bulk-import.

## Copyright statements

This software includes contributions from Elliot Sawyer, available under the LGPL3 license (with attribution). This attribution statement is required to be shipped with the module, and is displayed within your application. These will appear in certain areas of your application where the module is being used.

## Support

Like my work? Consider shouting me a coffee or a small donation if this module helped you solve a problem. I accept cryptocurrency at the following addresses:

* Bitcoin: 12gSxkqVNr9QMLQMMJdWemBaRRNPghmS3p
* Bitcoin Cash: 1QETPtssFRM981TGjVg74uUX8kShcA44ni
* Litecoin: LbyhaTESx3uQvwwd9So4sGSpi4tTJLKBdz
