<?php

namespace ALajusticia\SanctumTracker\Events;

use ALajusticia\SanctumTracker\Models\PersonalAccessToken;
use Illuminate\Queue\SerializesModels;

class PersonalAccessTokenCreated
{
    use SerializesModels;

    /**
     * The newly created PersonalAccessToken.
     *
     * @var PersonalAccessToken
     */
    public $personalAccessToken;

    /**
     * Informations about the request (user agent, ip address...).
     *
     * @var RequestContext
     */
    public $context;

    /**
     * Create a new event instance.
     *
     * @param PersonalAccessToken $personalAccessToken
     * @param RequestContext $context
     * @return void
     */
    public function __construct(PersonalAccessToken $personalAccessToken, RequestContext $context)
    {
        $this->personalAccessToken = $personalAccessToken;
        $this->context = $context;
    }
}
