<?php

namespace ALajusticia\SanctumTracker\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SanctumTracked
{
    /**
     * Revoke an access token by its ID.
     *
     * @param int|null $personalAccessTokenId
     * @return bool
     * @throws \Exception
     */
    public function logout($personalAccessTokenId = null)
    {
        $personalAccessToken = $personalAccessTokenId ? $this->tokens()->where('id', $personalAccessTokenId) : $this->currentAccessToken();

        return $personalAccessToken ? (!empty($personalAccessToken->delete())) : false;
    }

    /**
     * Revoke all access tokens, except the current one.
     *
     * @return mixed
     */
    public function logoutOthers()
    {
        $personalAccessTokens = $this->currentAccessToken() ? $this->tokens()->where('id', '<>', $this->currentAccessToken()->id) : null;

        return $personalAccessTokens ? (!empty($personalAccessTokens->delete())) : false;
    }

    /**
     * Destroy all sessions / Revoke all access tokens.
     *
     * @return mixed
     */
    public function logoutAll()
    {
        return $this->tokens()->delete();
    }
}
