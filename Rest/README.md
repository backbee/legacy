REST API
===============

Authentication
------------
BackBee uses public key encryption for API requests. All requests must provide
two custom HTTP headers:

* X-API-KEY - contains the user's public key
* X-API-SIGNATURE - contains an api signature generated for the request

The public and private API keys are set in the `user` database table.

Api Signature Generation
------------
Api signature is a SHA1-encoded string that represents the current request.

To generate an unencoded signature, the following request properties are
1) Full url path (without the query string), eg: `http://www.test.com/rest/products/product/1.json`
2) Query string params (if present), flattened to a string like so:
  a) Key/value pairs are sorted alphabetically by key
  b) If a value represents a collection/array, values in the collection must be
     sorted alphabetically, then converted to a string by joining all values
  c) For each key/value pair, the key and value are added to the flattened query
     string.
  Eg for query string `valueb[]=4&valueb[]=3&valuea=1`, the flattened string will
    be `valuea1valueb34`
3) HTTP method name - must be capitalized, eg: `PATCH`
4) Private API key, eg: `1234567890qwertyuiopasdfgh`

Therefore, the full unenecoded string that will be SHA1-encoded is:
`http://www.test.com/rest/products/product/1.jsonvaluea1valueb34PATCH1234567890qwertyuiopasdfgh`
which gives a SHA1 hash of `3533ee49900706750994209e0d92d208de1c86f5`


Security
-------------
```
firewalls:
    rest_api_area:
        pattern: ^/rest
        provider: public_key
        restful:  ~

providers:
    public_key:
        entity:
            class: BackBee\Security\User

encoders:
    BackBee\Security\User:
        class: BackBee\Security\Encoder\RequestSignatureEncoder
        arguments: []
```

