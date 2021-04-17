<?php

namespace ALajusticia\SanctumTracker\Parsers;

use ALajusticia\SanctumTracker\Interfaces\UserAgentParser;
use WhichBrowser\Parser;

class WhichBrowser implements UserAgentParser
{
    protected $parser;

    public function __construct()
    {
        $this->parser = new Parser(request()->userAgent());
    }

    /**
     * Get the device name.
     *
     * @return string
     */
    public function getDevice()
    {
        return trim($this->parser->device->toString()) ?: $this->getDeviceByManufacturerAndModel();
    }

    protected function getDeviceByManufacturerAndModel()
    {
        return trim($this->parser->device->getManufacturer().' '.$this->parser->device->getModel()) ?: null;
    }

    /**
     * Get the device type.
     *
     * @return string
     */
    public function getDeviceType()
    {
        return trim($this->parser->device->type) ?: null;
    }

    /**
     * Get the platform name.
     *
     * @return string
     */
    public function getPlatform()
    {
        return trim($this->parser->os->toString()) ?: null;
    }

    /**
     * Get the browser name.
     *
     * @return string
     */
    public function getBrowser()
    {
        return $this->parser->browser->name;
    }
}
