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

namespace BackBee\Theme;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\Theme\Exception\ThemeException;
use BackBee\Utils\File\Dir;
use BackBee\Utils\File\File;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class Theme extends ThemeConst
{
    /**
     * Site identifier.
     *
     * @var string
     */
    private $_site_uid;

    /**
     * Path of the theme folder.
     *
     * @var array
     */
    private $_themes_dir = array();

    /**
     * Renderer object.
     *
     * @var BBApplication
     */
    private $_application;

    /**
     * The template folder is valid (exist ? and is readable ?).
     *
     * @var boolean
     */
    private $_is_valid = false;

    /**
     * Current theme.
     *
     * @var PersonalThemeEntity
     */
    private $_personal_theme;

    /**
     * Current theme.
     *
     * @var ThemeEntity
     */
    private $_theme;

    /**
     * Current theme.
     *
     * @var ThemeEntity
     */
    private $_default_theme;

    /**
     * Directory Entity.
     *
     * @var array
     */
    private $_dir = array();

    /**
     * Config.
     *
     * @var BackBee\Config\Config
     */
    private $_config;

    /**
     * Theme object constructor.
     *
     * @param \BackBee\BBApplication $bbapp
     *
     * @throws ThemeException
     */
    public function __construct(BBApplication $bbapp = null)
    {
        if ($bbapp === null) {
            throw new ThemeException('Bad contruct implementation', ThemeException::THEME_BAD_CONSTRUCT);
        }
        $this->_application = $bbapp;
        $this->_site_uid = (null !== $bbapp->getSite()) ? $bbapp->getSite()->getUid() : null;
        $this->_themes_dir = $this->_application->getConfig()->getSection('themes_dir');

        $manager = new ThemesManager($this->getThemeDir(self::DEFAULT_NAME));
        $this->_default_theme = $manager->hydrateTheme($this->_application->getConfig()->getSection('theme'));
    }

    /**
     * Initialise alls the themes repository with their dependencies.
     */
    public function init()
    {
        $theme_repository = $this->_application->getEntityManager()->getRepository('BackBee\Theme\PersonalThemeEntity');
        $this->_personal_theme = $theme_repository->retrieveBySiteUid($this->_site_uid);

        if (is_object($this->_personal_theme) && $this->_personal_theme->getDependency() != self::DEFAULT_NAME) {
            $manager = new ThemesManager($this->getThemeDir(self::THEME_NAME));
            $this->_theme = $manager->getTheme($this->_personal_theme->getDependency());

            if (is_object($this->_theme)) {
                $this->_is_valid = $this->validateDirectory();
            }

            $this->_application->getConfig()->extend($this->getDirectory());
        }

        $this->build();
    }

    /**
     * Builds the selecteds themes for dispatch in BackBee.
     */
    public function build()
    {
        if ($this->getDefaultDirectory() != $this->getDirectory()) {
            $this->parseDirectory($this->_theme->getArchitecture(), $this->getDirectory());
        }
        if (is_object($this->_personal_theme)) {
            $this->parseDirectory($this->_personal_theme->getArchitecture(), $this->getPersonalDirectory());
        }
    }

    /**
     * Return the base theme folder.
     *
     * @param string $type
     *
     * @return Mixed
     */
    public function getThemeDir($type)
    {
        if (array_key_exists($type, $this->_themes_dir)) {
            $theme_dir = $this->_themes_dir[$type];
            $options = '/' === $theme_dir[0] ?
                array() :
                array(
                    'base_dir' => $this->_application->getBaseDir(),
                )
            ;
            File::resolveFilepath($theme_dir, null, $options);

            return $theme_dir.DIRECTORY_SEPARATOR;
        }

        return false;
    }

    /**
     * Return the path to the current theme if is valid else return the default theme.
     *
     * @return string
     */
    public function getDirectory()
    {
        if ($this->_is_valid) {
            $dir = $this->getThemeDir(static::THEME_NAME).$this->_theme->getFolder();
            return $dir;
        }

        return $this->getDefaultDirectory();
    }

    /**
     * Return the path to the default theme if exist.
     *
     * @return Mixed
     */
    public function getDefaultDirectory()
    {
        if (
            is_dir($this->getThemeDir(static::DEFAULT_NAME).$this->_default_theme->getFolder()) &&
            is_readable($this->getThemeDir(static::DEFAULT_NAME).$this->_default_theme->getFolder())
        ) {
            return $this->getThemeDir(static::DEFAULT_NAME).$this->_default_theme->getFolder();
        }

        return false;
    }

    /**
     * Return the path to the default theme if exist.
     *
     * @return string
     *
     * @throws ThemeException
     */
    public function getPersonalDirectory()
    {
        $site_folder = $this->_site_uid.DIRECTORY_SEPARATOR.$this->_personal_theme->getFolder();
        if (!is_dir($this->getThemeDir(static::PERSONAL_NAME).$site_folder)) {
            $sub_folder = mkdir($this->getThemeDir(static::PERSONAL_NAME).$this->_site_uid, 0700, true);
            $theme_folder = mkdir($this->getThemeDir(static::PERSONAL_NAME).$site_folder, 0700, true);

            if ($sub_folder && $theme_folder) {
                $manager = new PersonalThemesManager($this->getThemeDir(static::PERSONAL_NAME).$this->_site_uid);
                $manager->updateConfig($this->_personal_theme);
            } else {
                throw new ThemeException('Folder creation error', ThemeException::THEME_PATH_INCORRECT);
            }
        }

        return $this->getThemeDir(static::PERSONAL_NAME).$site_folder;
    }

    public function getIncludePath($name)
    {
        if (array_key_exists($name, $this->_dir)) {
            return $this->_dir[$name];
        }
    }

    /**
     * Parse the theme directory to know how kinde of elements the object need refernce.
     *
     * @param string $name path to parse
     */
    private function parseDirectory($architecture, $path)
    {
        $files = Dir::getContent($path);
        foreach ($files as $file) {
            $key = in_array($file, $architecture) ? array_search($file, $architecture) : false;
            if ($key) {
                $this->dispatchDirectory($architecture, $path, $key);
            }
        }
    }

    /**
     * Send to the object.
     *
     * @param string $path   current path
     * @param string $target target you need to dispatch
     */
    private function dispatchDirectory($architecture, $path, $target)
    {
        if ($target === static::SCRIPT_DIR) {
            $this->_application->getRenderer()->addScriptDir($path.DIRECTORY_SEPARATOR.$architecture[$target]);
        } elseif ($target === static::HELPER_DIR) {
            $this->_application->getRenderer()->addHelperDir($path.DIRECTORY_SEPARATOR.$architecture[$target]);
        } elseif ($target === static::LAYOUT_DIR) {
            $this->_application->getRenderer()->addLayoutDir($path.DIRECTORY_SEPARATOR.$architecture[$target]);
        } elseif ($target === static::LISTENER_DIR) {
            $this->_application->getAutoloader()->registerListenerNamespace($path.DIRECTORY_SEPARATOR.$architecture[$target]);
        } elseif (in_array($target, array(static::CSS_DIR, static::LESS_DIR, static::JS_DIR, static::IMG_DIR))) {
            if (!array_key_exists($target, $this->_dir)) {
                $this->_dir[$target] = array();
            }
            array_unshift($this->_dir[$target], $path.DIRECTORY_SEPARATOR.$architecture[$target]);
        }

        return true;
    }

    /**
     * Validate the theme directory.
     *
     * @return boolean
     */
    private function validateDirectory()
    {
        if (
                empty($this->_themes_dir[static::THEME_NAME]) ||
                !is_dir($this->getThemeDir(static::THEME_NAME).$this->_theme->getFolder()) ||
                !is_readable($this->getThemeDir(static::THEME_NAME).$this->_theme->getFolder())
        ) {
            return false;
        }

        return true;
    }

    /**
     * Init config.
     *
     * @return Theme
     */
    private function _initConfig($configdir = null)
    {
        if (null === $this->_application) {
            throw new \BackBee\Exception\MissingApplicationException('Missing BackBee application for theme');
        }

        if (is_null($configdir)) {
            $configdir = $this->getDirectory();
        }

        $this->_config = new Config($configdir, $this->_application->getBootstrapCache());

        return $this;
    }

    /**
     * Get config.
     *
     * @return \BackBee\Config\Config
     */
    public function getConfig()
    {
        if (null === $this->_config) {
            $this->_initConfig();
        }

        return $this->_config;
    }
}
