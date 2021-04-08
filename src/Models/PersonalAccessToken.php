<?php

namespace ALajusticia\SanctumTracker\Models;

use ALajusticia\SanctumTracker\Events\PersonalAccessTokenCreated;
use ALajusticia\SanctumTracker\RequestContext;
use ALajusticia\Expirable\Traits\Expirable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use Expirable, SoftDeletes;

    const EXPIRES_AT = 'expires_at';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expires_at',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'abilities' => 'json',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'abilities',
        'browser',
        'city',
        'country',
        'device',
        'device_type',
        'ip',
        'ip_data',
        'name',
        'platform',
        'region',
        'token',
        'user_agent',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_current',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($personalAccessToken) {

            // Get as much information as possible about the request
            $context = new RequestContext;

            $personalAccessToken->forceFill([
                'user_agent' => $context->userAgent,
                'ip' => $context->ip,
                'device_type' => $context->parser()->getDeviceType(),
                'device' => $context->parser()->getDevice(),
                'platform' => $context->parser()->getPlatform(),
                'browser' => $context->parser()->getBrowser(),
            ]);

            // If we have the IP geolocation data
            if ($context->ip()) {
                $personalAccessToken->forceFill([
                    'city' => $context->ip()->getCity(),
                    'region' => $context->ip()->getRegion(),
                    'country' => $context->ip()->getCountry(),
                ]);

                // Custom additional data?
                if (method_exists($context->ip(), 'getCustomData') &&
                    $context->ip()->getCustomData()) {

                    $personalAccessToken->ip_data = $context->ip()->getCustomData();
                }
            }

            // Set the expiration date
            if ($minutes = config('sanctum.expiration')) {
                $personalAccessToken->expiresAt(Carbon::now()->addMinutes($minutes));
            }

            // Dispatch event
            event(new PersonalAccessTokenCreated($personalAccessToken, $context));
        });
    }

    /**
     * Add the "location" attribute to get the IP address geolocation.
     *
     * @return string|null
     */
    public function getLocationAttribute()
    {
        $location = [
            $this->city,
            $this->region,
            $this->country,
        ];

        return array_filter($location) ? implode(', ', $location) : null;
    }

    /**
     * Dynamicly add the "is_current" attribute.
     *
     * @return bool
     */
    public function getIsCurrentAttribute()
    {
        return $this->id === request()->user()->currentAccessToken()->id;
    }
}
