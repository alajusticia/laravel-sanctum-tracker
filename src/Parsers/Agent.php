<?php

namespace ALajusticia\SanctumTracker\Parsers;

use ALajusticia\SanctumTracker\Interfaces\UserAgentParser;
use Jenssegers\Agent\Agent as Parser;

class Agent implements UserAgentParser
{
    protected $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Get the device name.
     *
     * @return string
     */
    public function getDevice()
    {
        return $this->parser->device();
    }

    /**
     * Get the device type.
     *
     * @return string
     */
    public function getDeviceType()
    {
        if ($this->parser->isDesktop()) {
            return 'desktop';
        } elseif ($this->parser->isMobile()) {
            return $this->parser->isTablet() ? 'tablet' : $this->parser->isPhone() ? 'phone' : 'mobile';
        }

        return null;
    }

    /**
     * Get the platform name.
     *
     * @return string
     */
    public function getPlatform()
    {
        return $this->parser->platform();
    }

    /**
     * Get the browser name.
     *
     * @return string
     */
    public function getBrowser()
    {
        return $this->parser->browser();
    }
}
