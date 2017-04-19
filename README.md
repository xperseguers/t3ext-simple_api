# Simple API for TYPO3

Service to route HTTP/REST requests to your own controllers.

**BEWARE:** This extension is expected to be used by TYPO3 developers and acts as a central hub to route requests to
your own business logic.


## Features

- Support for authenticated method calls
- Support for localized calls (taking the language into account)
- Support for cached calls and transparent access to the TYPO3 caching framework within your API handler
- Support for gzip payload if header HTTP_ACCEPT_ENCODING is present and contains `gzip`
- Support for dynamically generating a documentation of your API


## Difference with EXT:routing

Unlike [EXT:routing](https://github.com/xperseguers/t3ext-routing), this extension does not force you map Extbase
controller/actions to route segments but instead basically let's you register a "segment" (typically the first one) and
then will simply route the whole request to a `handle()` method within your controller.


## Registration of handlers

First of all you should add a dependency within your `ext_emconf.php` configuration file, either as a real constraint,
or as a suggestion, depending on what you prefer:

```
$EM_CONF[$_EXTKEY] = [
    // snip
    'constraints' => [
        'depends' => [
            'php' => '5.5.0-7.1.99',
            'typo3' => '7.6.0-8.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'simple_api' => '',
        ],
    ],
];
```

Then, within `ext_localconf.php`:

```
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiHandlers'][] = [
    'route' => '/some-route',
    'class' => \VENDOR\YourExt\Api\YourClass::class,
];
```

you may register a pattern instead of a fixed route:

```
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['simple_api']['apiPatternHandlers'][] = [
    'route' => '/members/\d+/history',
    'class' => \VENDOR\YourExt\Api\YourClass::class,
];
```

Your handler must extend `\Causal\SimpleApi\Controller\AbstractHandler`.


### Handler Keys

The registration array supports various keys:

- **route** [*mandatory*]: The route to register.

- **class** [*mandatory*]: The class handling requests to the corresponding route.

- **contentType** [*optional*]: Content type of the payload accepted by a POST request, will decode it automatically
  before invoking your handler.

- **methods** [*optional*]: The comma-separated list of HTTP methods accepted by the handler (e.g., "POST"). Defaults to
  no restrictions.

- **restricted** [*optional*]: Whether the API call expects an authenticated call (using "HTTP_X_AUTHORIZATION" header).
  If restricting access to part of your API, you **must** register a route with name `/authenticate` which will get the
  HTTP_X_AUTHORIZATION header, do something with it and return an array with following keys:
  
  - `success => true` (or `false`). Will be passed as `_authenticated` boolean flag to the API handler
  - Custom keys will be prefixed by `_` and pass as-is to the API handler (e.g., `user` will become `_user`)
  
  **Hint:** If HTTP_X_AUTHORIZATION header is present, the authentication will take place and your handler will be
  invoked regardless of the outcome of the call, if you did not explicitely marked your handler as "restricted".

- **deprecated** [*optional*]: Boolean flag to mark the corresponding route as deprecated in the documentation.


### Output Payload

Following rules apply with the payload you return from your API handler:

- Payload is expected to be an array and will as content-type `application/json`. If you want to return another
  content-type (such as an image), you should do it in your own API handler and `exit()` afterwards.
- If an exception is thrown, it is catched and encapsulated into a HTTP 500 error. The only exception is if exception
  `\Causal\SimpleApi\Exception\ForbiddenException` is thrown, it will throw a HTTP 403 error instead.
- If no handlers are found, a HTTP 404 error is returned.
