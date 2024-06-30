# Troubleshooting

## When I run the index task, some records are being skipped.

Typesense will validate uploaded data before inserting it into the collection, according to the schema that you've provided to it. This means if it receives data where even one field doesn't match what is expected in the schema, the entire record gets skipped.

One common example is from the docs, where you're maybe indexing the pages on your site but some do not have Content to display.  If the field is not marked as `optional: true`, Typesense will not accept the record if the field is empty.
