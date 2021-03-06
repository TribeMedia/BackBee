<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Application;

use Symfony\Component\HttpFoundation\ParameterBag;
use BackBee\BBApplication;

/**
 * Application Analytics service.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      ken.golovin
 */
class Analytics
{
    /**
     * @var ParameterBag
     */
    protected $params;

    /**
     * @var bool
     */
    protected $initialised = false;

    /**
     * @var BBApplication
     */
    protected $bbapp;

    public function __construct(BBApplication $bbapp)
    {
        $this->bbapp = $bbapp;
        $this->params = new ParameterBag();
    }

    /**
     * @return BBApplication
     */
    public function getApplication()
    {
        return $this->bbapp;
    }

    /**
     * @see ParameterBag::get
     */
    public function get($path, $default = null)
    {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->params->get($path, $this->getGlobalConfigData($path), true);
    }

    /**
     * @return ParameterBag
     */
    public function getParams()
    {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->params;
    }

    /**
     * @see ParameterBag::set
     */
    public function set($key, $value)
    {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->params->set($key, $value);
    }

    protected function getGlobalConfigData($key, $default = null)
    {
        // try site config
        $site = $this->bbapp->getContainer()->get('site');
        $sitesConfig = $this->bbapp->getConfig()->getSection('sites');

        if (isset($sitesConfig[$site->getLabel()]['analytics'][$key])) {
            return $sitesConfig[$site->getLabel()]['analytics'][$key];
        }

        // try global config
        $analytics = $this->bbapp->getConfig()->getSection('analytics');

        return isset($analytics[$key]) ? $analytics[$key] : $default;
    }

    protected function initialise()
    {
    }
}
