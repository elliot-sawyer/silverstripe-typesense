## Configuration

You need a few environment variables defined:
```
TYPESENSE_API_KEY="......"
TYPESENSE_SERVER="......"
TYPESENSE_SEARCH_KEY="......"
```

* TYPESENSE_API_KEY can be any random text, but must be kept secret. Anybody with this key has full administrator control over your Typesense installation.  It can be generated with `head /dev/urandom | shasum -a 256`
* TYPESENSE_SERVER is an _externally facing_ hostname: it may work internally, but users will be sending requests directly to it so it must be accessible in a browser.  For example `http://localhost:8108` may work for you, but if your environment is not publicly listening on 8108 the request may fail.
* TYPESENSE_SEARCH_KEY is used for front-end searches (so read-only) and exposed publicly to users. More information about this can be found in the [searching section](07-searching.md)

You can, of course, use an externally hosted Typesense server using a service such as [Typesense Cloud](https://cloud.typesense.org).

