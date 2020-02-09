# Laravel Auth Tracker

#### Track and manage sessions, Passport tokens and Airlock tokens in Laravel.

This package allows you to track separately each login (session or token), attaching informations by parsing the
User-Agent and saving the IP address.

Using a supported provider or creating your own custom providers, you can collect even more informations with
an IP address lookup to get, for example, the geolocation.

You can revoke every single login or all at once. In case of sessions with remember tokens, every session has its
own remember token. This way, you can revoke a session without affecting the others. It solves this
[issue](https://github.com/laravel/ideas/issues/971).

* [Compatibility](#compatibility)
* [Installation](#installation)
  * [Create the logins table](#create-the-logins-table)
  * [Prepare your authenticatable models](#prepare-your-authenticatable-models)
  * [Prepare your LoginController](#prepare-your-logincontroller)
  * [Choose and install a user-agent parser](#choose-and-install-a-user-agent-parser)
  * [Configure the user provider](#configure-the-user-provider)
  * [Generate the scaffolding](#generate-the-scaffolding)
  * [Laravel Airlock](#laravel-airlock)
* [Usage](#usage)
  * [Retrieving the logins](#retrieving-the-logins)
    * [Get all the logins](#get-all-the-logins)
    * [Get the current login](#get-the-current-login)
  * [Check for the current login](#check-for-the-current-login)
  * [Revoking logins](#revoking-logins)
    * [Revoke a specific login](#revoke-a-specific-login)
    * [Revoke all the logins](#revoke-all-the-logins)
    * [Revoke all the logins except the current one](#revoke-all-the-logins-except-the-current-one)
* [Routes](#routes)
* [Events](#events)
  * [Login](#login)
* [IP address lookup](#ip-address-lookup)
  * [Custom provider](#custom-provider)
  * [Handle API errors](#handle-api-errors)
* [Blade directives](#blade-directives)
* [License](#license)

## Compatibility

- This package has been tested with **Laravel >= 5.8**.

- It works with all the session drivers supported by Laravel, except of course the cookie driver which saves
the sessions only in the client browser and the array driver.

- To track API tokens, it supports the official **Laravel Passport (>= 7.5)** and **Laravel Airlock (v0.2.0)** packages.

- In case you want to use Passport with multiple user providers, this package works with the
`sfelix-martins/passport-multiauth` package (see [here](https://github.com/sfelix-martins/passport-multiauth)).

## Installation

Install the package with composer:

```bash
composer require alajusticia/laravel-auth-tracker
```

Publish the configuration file (`config/auth_tracker.php`) with:

```bash
php artisan vendor:publish --provider="ALajusticia\AuthTracker\AuthTrackerServiceProvider" --tag="config"
```

### Create the logins table

Before running the migrations, you can change the name of the table that will be used to save the logins
(named by default `logins`) with the `table_name` option of the configuration file.

Launch the database migrations to create the required table:

```bash
php artisan migrate
```

### Prepare your authenticatable models

In order to track the logins of your app's users, add the `ALajusticia\AuthTracker\Traits\AuthTracking` trait
on each of your authenticatable models that you want to track:

```php
use ALajusticia\AuthTracker\Traits\AuthTracking;
use Illuminate\Foundation\Auth\User as Authenticatable;
// ...

class User extends Authenticatable
{
    use AuthTracking;

    // ...
}
```

### Prepare your LoginController

Replace the `Illuminate\Foundation\Auth\AuthenticatesUsers` trait of your `App\Http\Controllers\Auth\LoginController`
by the `ALajusticia\AuthTracker\Traits\AuthenticatesWithTracking` trait provided by this package.

This trait overrides the `sendLoginResponse` method by removing the session regeneration.
But don't worry, there's no security issue here.
Instead, this package do the session regeneration in an event
listener on the login event (before saving the informations of the new login).
Because of the `sendLoginResponse` regenerating the session ID after the login event has been dispatched,
this approach allows to get the right session ID generated by a new login.

### Choose and install a user-agent parser

This package relies on a User-Agent parser to extract the informations.

Currently, it supports two of the most popular parsers:
- WhichBrowser ([https://github.com/WhichBrowser/Parser-PHP](https://github.com/WhichBrowser/Parser-PHP))
- Agent ([https://github.com/jenssegers/agent](https://github.com/jenssegers/agent))

Before using the Auth Tracker, you need to choose a supported parser, install it and indicate in the configuration file which one you want
to use.

### Configure the user provider

This package comes with a modified Eloquent user provider that retrieve remembered users from the logins table instead of the users table.

In your `config/auth.php` configuration file, use the `eloquent-extended` driver in the user providers list for the users you want to track:

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent-extended',
        'model' => App\User::class,
    ],
    
    // ...
],
```

### Generate the scaffolding

This step is optional but can help you getting started by generating the scaffolding of the Auth Tracker.

Launch this command:

```bash
php artisan tracker:install
```

This command will:

- publish the controller `AuthTrackingController` in `app/Http/Controllers/Auth`
- publish the view `list.blade.php` in `resources/views/auth`
- add routes in `routes/web.php` via the `Route::authTracker()` macro (see all the available [routes](#routes))

Now, log in with a tracked user and go to `/security`. You will find a page to manage the logins! 

### Laravel Airlock

In the actual version (0.2.0) of the Laravel Airlock package, there is no event allowing us to know when
an API token is created.

If you are issuing API tokens with Laravel Airlock and want to enable auth tracking,
you will have to dispatch an event provided by the Auth Tracker.

Dispatch the `PersonalAccessTokenCreated` event when you create an access token with Laravel Airlock, passing
the access token newly created via the `createToken` method of the `Laravel\Airlock\HasApiTokens` trait.

Based on the [example](https://github.com/laravel/airlock#authenticating-mobile-applications) provided by
the Laravel Airlock documentation, it might look like this:

```php
use ALajusticia\AuthTracker\Events\PersonalAccessTokenCreated;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::post('/airlock/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $newAccessToken = $user->createToken($request->device_name);
    
    event(new PersonalAccessTokenCreated($newAccessToken));

    return $newAccessToken->plainTextToken;
});
```

## Usage

The `AuthTracking` trait provided by this package surcharge your users models with methods to list their logins and to
give you full individual control on them.

### Retrieving the logins

#### Get all the logins

```php
$logins = request()->user()->logins;
```

#### Get the current login

```php
$login = request()->user()->currentLogin;
```

### Check for the current login

Each login instance comes with a dynamic `is_current` attribute.
It's a boolean that indicates if the login instance is the current login.

### Revoking logins

#### Revoke a specific login

To revoke a specific login, use the `logout` method with the ID of the login you want to revoke.
If no parameter is given, the current login will be revoked.

```php
request()->user()->logout(1); // Revoke the login where id=1
```

```php
request()->user()->logout(); // Revoke the current login
```

#### Revoke all the logins

We can destroy all the sessions and revoke all the Passport tokens by using the `logoutAll` method.
Useful when, for example, the user's password is modified and we want to logout all the devices.

This feature destroys all sessions, even those remembered.

```php
request()->user()->logoutAll();
```

#### Revoke all the logins except the current one

The `logoutOthers` method acts in the same way as the `logoutAll` method except that it keeps the current
session / Passport token alive.

```php
request()->user()->logoutOthers();
```

## Routes

Here are the routes added by the scaffolding command:

```php
Route::prefix($prefix)->group(function () {
                
    // Route to manage logins
    Route::get('/', 'Auth\AuthTrackingController@listLogins')->name('login.list');

    // Logout routes
    Route::middleware('auth')->group(function () {
        Route::post('logout/all', 'Auth\AuthTrackingController@logoutAll')->name('logout.all');
        Route::post('logout/others', 'Auth\AuthTrackingController@logoutOthers')->name('logout.others');
        Route::post('logout/{id}', 'Auth\AuthTrackingController@logoutById')->where('id', '[0-9]+')->name('logout.id');
    });
});
```

## Events

### Login

On a new login, you can listen to the event `ALajusticia\AuthTracker\Events\Login`.
It receives a `RequestContext` object containing all the informations collected on the request, accessible on the event
with the `context` property.

Properties available:
```php
$this->context->userAgent; // The full, unparsed, User-Agent header
$this->context->ip; // The IP address
```

Methods available:
```php
$this->context->parser(); // Returns the parser used to parse the User-Agent header
$this->context->ip(); // Returns the IP address lookup provider
```

Methods available in the parser:
```php
$this->context->parser()->getDevice(); // The name of the device (MacBook...)
$this->context->parser()->getDeviceType(); // The type of the device (desktop, mobile, tablet, phone...)
$this->context->parser()->getPlatform(); // The name of the platform (macOS...)
$this->context->parser()->getBrowser(); // The name of the browser (Chrome...)
```

Methods available in the IP address lookup provider:
```php
$this->context->ip()->getCountry(); // The name of the country
$this->context->ip()->getRegion(); // The name of the region
$this->context->ip()->getCity(); // The name of the city
$this->context->ip()->getResult(); // The entire result of the API call as a Laravel collection

// And all your custom methods in the case of a custom provider
```

## IP address lookup

By default, the Auth Tracker collects the IP address and the informations given by the User-Agent header.

But you can go even further and collect other informations about the IP address, like the geolocation.

To do so, you first have to enable the IP lookup feature in the configuration file.

For now, this package comes with one officially supported provider for IP address lookup.

### Custom provider

You can add your own providers by creating a class that implements the
`ALajusticia\AuthTracker\Interfaces\IpProvider` interface and use the
`ALajusticia\AuthTracker\Traits\MakesApiCalls` trait.

Your custom class have to be registered in the `custom_providers` array of the configuration file.

Let's see an example of an IP lookup provider with the built-in `IpApi` provider:

```php
use ALajusticia\AuthTracker\Interfaces\IpProvider;
use ALajusticia\AuthTracker\Traits\MakesApiCalls;
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

As you can see, the class have a `getRequest` method that must return a `GuzzleHttp\Psr7\Request` instance.

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

## Blade directives

Check if the auth tracking is enabled for the current user:

```php
@tracked
    <a href="{{ route('login.list') }}">Security</a>
@endtracked
```

Check if the IP lookup feature is enabled:

```php
@ipLookup
    {{ $login->location }}
@endipLookup
```

## License

Open source, licensed under the [MIT license](LICENSE).
