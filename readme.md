# EdgestashBlade

The *EdgestashBlade* is a Laravel package that adds *Edgestash* support to Blade.

[Edgestash](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/) is a [Varnish Enterprise](https://www.varnish-software.com/solutions/varnish-enterprise/) module that processes [Mustache templates](https://mustache.github.io/) on the edge.

Edgestash allows for JSON data to be composed into a response using Mustache syntax, ex: `{{variable}}`. Each response can be assigned its own JSON object for truly dynamic responses. JSON can either be fetched from a backend or generated via VCL and any number of JSONs can be assigned to a template.

This package offers a couple of custom Blade directives that facilitate the use of Edgestash in an unobtrusive way, allowing you to efficiently cache personalized data.

## Installation

You can install `EdgestashBlade` with [Composer](https://getcomposer.org) and this package [listed on Packagist](https://packagist.org/packages/thijsferyn/edgestash-blade). Please run the following command to install the package in your Laravel application:

```
composer require thijsferyn/edgestash-blade
```

Once the dependencies are installed, you will need to register `ThijsFeryn\EdgestashBlade\Middleware\EdgestashBladeMiddleware` as middleware in your `Kernel.php`

## How does Edgestash work?

[The VCL example on the Edgestash documentation page](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/#vcl-example) shows how the Mustache template and the JSON data are cached separately.

> VCL stands for [*Varnish Configuration Language*](https://varnish-cache.org/docs/trunk/reference/vcl.html) and is the domain-specific programming language that controls the behavior of Varnish. 

The `edgestash.parse_response()` method will process the template and identify the placeholders that require parsing.

Edgestash offers 3 different ways to ingest JSON data used to replace the template placeholders:

* [add_json_url](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/#add-json-url): Pass a URL that refers to a JSON endpoint
* [add_json_url_csv](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/#add-json-url-csv): Pass a CSV-style list of URLs that refer to multiple JSON endpoints
* [add_json](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/#add-json): Pass the JSON as a string

Once the JSON is injected, the `edgestash.execute()` method is executed, the turn the JSON and the template into personalized output.

## How do you trigger Edgestash from Blade templates?
### The `@edgestash` directive

The custom `@edgestash` Blade directive will emit an unparsed Mustache variable, will perform the content negotiation with Varnish and will automatically provide the JSON endpoint.

Here's an example:

```
<div>@edgestash('username','/session')</div>
```

This example will emit a `{{ username }}` variable. Although Blade has similar syntax to Mustache, the variable will not be parsed by Blade.

The `/session` endpoint containing JSON data will be sent to Varnish. Varnish will process that JSON data, look for the `username` property and parse it into the `{{ username }}` variable.

### The `@edgestashIfDetected` directive

The `@edgestashIfDetected` directive is similar to the `@edgestash` directive, but it adds a fail safe: if Edgestash is not automatically detected, the value of the 1st argument is returned instead.

Here's an example:

```
<div>@edgestashIfDetected($username,'username','/session')</div>
```

In this example, `@edgestashIfDetected` will return the `$username` variable if it didn't detect an Edgestash-supported Varnish server.  

However, if it did detect an Edgestash-supported Varnish server, it will output `{{ username }}` and and send the `/session` endpoint to Varnish.

### The `@isEdgestash` conditional directive

Although the `@edgestash` directive is an ideal way to introduce Edgestash in a non-obtrusive way, unfortunately sometimes Edgestash integration requires some custom template code.

Fortunately, the `@isEdgestash` conditional directive allows you to detect whether or not the application sits behind and Edgestash-supported Varnish server. This is the same logic that the `@edgestashIfDetected` directive uses under the hood for detection.

Here's an example:

```
<div>
    @isEdgestash
        //Edgestash-supported logic
    @else
        //Regular Blade logic
    @endisEdgestash
</div>
```  

### Mustache conditions and loops

[Edgestash supports the full Mustache syntax](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash/#variables-and-expressions) and can handle conditions and loops.

Here's an example of an if-else-statement using Edgestash:

```
@edgestash('#username', '/session')
  Welcome @edgestash('username')
@edgestash('/username')
@edgestash('^username')
    Welcome guest
@edgestash('/username')
``` 

So if the `username` property is detected in the JSON data, the username will be printed. If not, we'll refer to the user as a *guest*.

> Please not that a JSON endpoint should only be registered once. Subsequently, the `url` argument can be left blank. However, if the same endpoint is registered multiple times, it will cause no harm since duplicates are removed.

Here's an example of a loop using Edgestash:

```
<ul>
    @edgestash('#.', '/users')
    <li>@edgestash('username')</li>
    @edgestash('/.')
</ul>
```
In this example, we loop over a collection of user objects and list the usernames. The output of the `/users` endpoint could look like this:

```
[
    {"username":"user 1"},
    {"username":"user 2"},
    {"username":"user 3"}
]
```

The final output would then be:

```
<ul>
    <li>user 1</li>
    <li>user 2</li>
    <li>user 3</li>
</ul>
```

### Multiple JSON endpoints

This Edgestash integration allows for multiple JSON endpoints. Duplicate values are removed and the output of all endpoints is merged into a single JSON object.

Here's an example:

```
<div>@edgestashIfDetected($username, 'username','/session')</div>
<div>@edgestashIfDetected($items_in_cart,'items_in_cart','/cart')</div>
```

## How does the `EdgestashBlade` interact with Varnish?

As previously mentioned, the `edgestash` filter works in a non-obtrusive way. This means it can detect whether or not it sits behind an Edgestash-supported Varnish server.

### Edgestash detection

In order to announce Edgestash support, Varnish will send the following request header to your origin server containing the application:

`Surrogate-Capability: edgestash="EDGESTASH/2.1"`

This Laravel package looks for this header and if it finds it, Edgestash is supported.

On the way back, Varnish needs to know when it should consider the output to be a Mustache template. The `EdgestashBlade` bundle does this by sending the following response header back to Varnish:

`Surrogate-Control: edgestash="EDGESTASH/2.1"`

When Varnish detects this response header, it executes the `edgestash.parse_response()` method internally.

> Detection happens automatically and the header negotiation is done in [EdgestashBladeMiddleware.php](/src/EdgestashBladeMiddleware.php)

### Processing JSON endpoints

When the `edgestash` directive is invoked with a valid URL argument, the URL is added as an internal HTTP request argument.

The argument is processed by [EdgestashBladeServiceProvider.php](/src/EdgestashBladeServiceProvider.php) and the URL (or URLs) are exposed using a `Link` header.

Here's an example of such a `Link` header:

```
Link: </session>; rel=edgestash
``` 

The link header has a `rel=edgestash` property, making it easy to find when the `Link` header contains other non-related data.

This is what it would look like when multiple JSON endpoints are registered:

```
Link: </session>; rel=edgestash, </cart>; rel=edgestash
```

When Edgestash output is detected by Varnish and the `Link` header is parsed, Varnish will trigger the `edgestash.add_json_url_csv` method to retrieve the JSON data through the endpoints. And finally the `edgestash.execute()` method is executed that parses the JSON and adds it to the cached template's placeholders.

## Varnish requirements

[Edgestash](https://docs.varnish-software.com/varnish-cache-plus/vmods/edgestash) is a proprietary VMOD that is developed and maintained by [Varnish Software](https://www.varnish-software.com). This module is automatically packaged when you sign-up for [Varnish Enterprise](https://www.varnish-software.com/solutions/varnish-enterprise/).

If you do not have a *Varnish Enterprise* license, you can also try out Varnish Enterprise and Edgestash using one of our [Cloud images](https://www.varnish-software.com/products/varnish-cloud/) on AWS, Azure or Google Cloud.

The minimal VCL required to use the `EdgestashBlade` is stored in [default.vcl](/default.vcl)
