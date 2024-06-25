# silverstripe-typesense

New home for the Silverstripe Typesense project

## Installation

## Configuration

## Creating a collection

Collections are defined and initially created with YML:

```yml
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    Page:
      name: Pages
      fields:
        - { name: Title, type: string, sort: true }
        - { name: Content, type: string }
        - { name: Link, type: string, index: false, optional: true}
      default_sorting_field: # 'exampleField'
      token_separators: # '-'
      symbols_to_index: # '+'
      excludedClasses:
        - SilverStripe\ErrorPage\ErrorPage
```

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

Collections can also be created, managed, and deleted in the CMS. What's in the database is considered the "source of truth". If a collection is deleted on the Typesense server, it will be regenerated when TypesenseSyncTask is run.

If a collection already exists in the database, the YML field not create or update it.

If a collection is removed from YML, but exists in the database, it will not be removed from the database.

## Copyright statements

This software includes contributions from Elliot Sawyer, available under the GPL3 license (with attribution). This attribution statement is required to be shipped with the module, and displayed within your application. These will appear in certain areas of your application where the module is being used
