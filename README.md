# Laravel Sanctum Tracker

#### Track Sanctum tokens in Laravel.

This package allows you to track Sanctum tokens, attaching informations by parsing the User-Agent and saving the IP address.

Using a supported provider or creating your own custom providers, you can collect even more informations with
an IP address lookup to get, for example, the geolocation.

It also provides a trait introducing convenient methods: `logout`, `logoutOthers` and `logoutAll` for your user model.

* [Compatibility](#compatibility)
* [Installation](#installation)
  * [Override default model](#override-default-model)
  * [Choose and install a user-agent parser](#choose-and-install-a-user-agent-parser)
  * [Add the trait to your user model (optional)](#add-the-trait-to-your-user-model-optional)
* [Usage](#usage)
* [Events](#events)
  * [PersonalAccessTokenCreated](#personalaccesstokencreated)
* [IP address lookup](#ip-address-lookup)
  * [Ip2Location Lite DB3](#ip2location-lite-db3)
  * [Custom provider](#custom-provider)
  * [Handle API errors](#handle-api-errors)
* [Events](#events)
  * [PersonalAccessTokenCreated](#personalaccesstokencreated)
* [License](#license)

## Compatibility

- This package has been tested with **Laravel 8.x** and **Laravel Sanctum (v2)**.

## Installation

Install the package with composer:

```bash
composer require alajusticia/laravel-sanctum-tracker
```

Publish the configuration file (`config/sanctum_tracker.php`) with:

```bash
php artisan vendor:publish --provider="ALajusticia\SanctumTracker\SanctumTrackerServiceProvider" --tag="config"
```

### Override default model

This package comes with a custom model (`ALajusticia\SanctumTracker\Models\PersonalAccessToken`) that extends the default Sanctum model.

Instruct Sanctum to use this custom model via the `usePersonalAccessTokenModel` method provided by Sanctum. Typically, you should call this method in the `boot` method of one of your application's service providers:

```php
use ALajusticia\SanctumTracker\Models\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
}
```

### Choose and install a user-agent parser

This package relies on a User-Agent parser to extract the informations.

Currently, it supports two of the most popular parsers:
- WhichBrowser ([https://github.com/WhichBrowser/Parser-PHP](https://github.com/WhichBrowser/Parser-PHP))
- Agent ([https://github.com/jenssegers/agent](https://github.com/jenssegers/agent))

Before using the Sanctum Tracker, you need to choose a supported parser, install it and indicate in the configuration file which one you want
to use.

### Add the trait to your user model (optional)

This package provides a `ALajusticia\SanctumTracker\Traits\SanctumTracked` trait
that can be used on your user model to quickly revoke Sanctum tokens.

It introduces convenient methods:

- `logout`: to revoke the current token or a specific token by passing its ID in parameter
- `logoutOthers`: to revoke all the tokens, except the current one
- `logoutAll`: to revoke all the tokens, including the current on

```php
use ALajusticia\SanctumTracker\Traits\SanctumTracked;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use SanctumTracked;

    // ...
}
```

## Usage

Issue Sanctum tokens like you would normally do. The `PersonalAccessToken` model provided by this package will automatically be populated with the extra informations.

## Events

### PersonalAccessTokenCreated

On a new login, you can listen to the `ALajusticia\SanctumTracker\Events\PersonalAccessTokenCreated` event.
It has a `personalAccessToken` property containing the newly created `ALajusticia\SanctumTracker\Models\PersonalAccessToken` and a `context` property that receives a `ALajusticia\SanctumTracker\RequestContext` object containing all the informations collected on the request.

Available properties:
```php
$this->context->userAgent; // The full, unparsed, User-Agent header
$this->context->ip; // The IP address
```

Available methods:
```php
$this->context->parser(); // Returns the parser used to parse the User-Agent header
$this->context->ip(); // Returns the IP address lookup provider
```

Available methods in the parser:
```php
$this->context->parser()->getDevice(); // The name of the device (MacBook...)
$this->context->parser()->getDeviceType(); // The type of the device (desktop, mobile, tablet, phone...)
$this->context->parser()->getPlatform(); // The name of the platform (macOS...)
$this->context->parser()->getBrowser(); // The name of the browser (Chrome...)
```

Available methods in the IP address lookup provider:
```php
$this->context->ip()->getCountry(); // The name of the country
$this->context->ip()->getRegion(); // The name of the region
$this->context->ip()->getCity(); // The name of the city
$this->context->ip()->getResult(); // The entire result of the API call as a Laravel collection

// And all your custom methods in the case of a custom provider
```

## IP address lookup

By default, the Sanctum Tracker collects the IP address and the informations given by the User-Agent header.

But you can go even further and collect other informations about the IP address, like the geolocation.

To do so, you first have to enable the IP lookup feature in the configuration file.

This package comes with two officially supported providers for IP address lookup
(see the IP Address Lookup section in the `config/sanctum_tracker.php` configuration file).

### Ip2Location Lite DB3

This package officially support the IP address geolocation with the Ip2Location Lite DB3.

Here are the steps to enable and use it:

- Download the current version of the database and import it in your database as explained in the documentation:
[https://lite.ip2location.com/database/ip-country-region-city](https://lite.ip2location.com/database/ip-country-region-city)

- Set the name of the `ip_lookup.provider` option to `ip2location-lite` in the `config/sanctum_tracker.php` configuration file

- Indicate the name of the tables used in your database for IPv4 and IPv6 in the `config/sanctum_tracker.php` configuration file
(by default it uses the same names as the documentation: `ip2location_db3` and `ip2location_db3_ipv6`)

### Custom provider

You can add your own providers by creating a class that implements the
`ALajusticia\SanctumTracker\Interfaces\IpProvider` interface and use the
`ALajusticia\SanctumTracker\Traits\MakesApiCalls` trait.

Your custom class have to be registered in the `custom_providers` array of the configuration file.

Let's see an example of an IP lookup provider with the built-in `IpApi` provider:

```php
use ALajusticia\SanctumTracker\Interfaces\IpProvider;
use ALajusticia\SanctumTracker\Traits\MakesApiCalls;
use GuzzleHttp\Psr7\Request;

class IpApi implements IpProvider
{
    use MakesApiCalls;

    /**
     * Get the Guzzle request.
     *
     * @return Request
     */
    public function getRequest()
    {
        return new Request('GET', 'http://ip-api.com/json/'.request()->ip().'?fields=25');
    }

    /**
     * Get the country name.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->result->get('country');
    }

    /**
     * Get the region name.
     *
     * @return string
     */
    public function getRegion()
    {
        return $this->result->get('regionName');
    }

    /**
     * Get the city name.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->result->get('city');
    }
}
```

As you can see, the class has a `getRequest` method that must return a `GuzzleHttp\Psr7\Request` instance.

Guzzle utilizes PSR-7 as the HTTP message interface. Check its documentation:
[http://docs.guzzlephp.org/en/stable/psr7.html](http://docs.guzzlephp.org/en/stable/psr7.html)

The `IpProvider` interface comes with required methods related to the geolocation.
All keys of the API response are accessible in your provider via `$this->result`, which is a Laravel collection.

If you want to collect other informations, you can add a `getCustomData` method in your custom provider.
This custom data will be saved in the logins table in the `ip_data` JSON column.
Let's see an example of additional data:

```php
public function getCustomData()
{
    return [
        'country_code' => $this->result->get('countryCode'),
        'latitude' => $this->result->get('lat'),
        'longitude' => $this->result->get('lon'),
        'timezone' => $this->result->get('timezone'),
        'isp_name' => $this->result->get('isp'),
    ];
}
```

### Handle API errors

In case of an exception throwed during the API call of your IP address lookup provider, the FailedApiCall event
is fired by this package.

This event has an exception attribute containing the GuzzleHttp\Exception\TransferException
(see [Guzzle documentation](http://docs.guzzlephp.org/en/stable/quickstart.html#exceptions)).

You can listen to this event to add your own logic.

## License

Open source, licensed under the [MIT license](LICENSE).
