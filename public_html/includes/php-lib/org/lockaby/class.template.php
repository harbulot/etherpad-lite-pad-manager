<?php
namespace org\lockaby;

/**
 * Project:    QuickXSL
 * File:       class.template.php
 * Author:     Paul Lockaby <paul@paullockaby.com>
 * Version:    1.0.0
 * Copyright:  2005 - 2006 by Paul Lockaby
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * You may contact the author of LightTemplate by e-mail at:
 * paul@paullockaby.com
 *
 */

class template {
    // public configuration variables
    private $template_dir = "templates"; // where the templates are to be found
    private $plugin_dir = "plugins"; // where the plugins are to be found

    // caching
    private $cache = false; // turn caching on?
    private $cache_dir = "cached"; // where cache files are stored
    private $cache_lifetime = 0; // how long the file in cache should be considered "fresh"

    // private internal variables
    private $_vars = array(); // stores all internal assigned variables
    private $_xml = null; // stores whatever the xml source is
    private $_plugins = array(); // stores all internal plugins
    private $_cache_id = null;
    private $_cache_dir = ""; // stores where this specific file is going to be cached
    private $_cache_info = null;
    private $_error_level;

    public function setCacheLocation($path) {
        $this->cache_dir = $path;
    }

    public function setTemplateLocation($path) {
        $this->template_dir = $path;
    }

    public function setPluginLocation($path) {
        $this->plugin_dir = $path;
    }

    public function loadXMLFromString(&$xml_string) {
        $this->_xml = array("type" => "string", "value" => $xml_string);
    }

    public function loadXMLFromFile($xml_file) {
        $this->_xml = array("type" => "file", "value" => $xml_file);
    }

    public function setParameter($key, $value = null) {
        if (is_array($key)) {
            foreach($key as $var => $val) {
                if ($var != "" && !is_numeric($var)) {
                    $this->_vars[$var] = (string)$val;
                }
            }
        } else {
            if ($key != "" && !is_numeric($key))
                $this->_vars[$key] = (string)$value;
        }
    }

    public function getParameter($key = null) {
        if ($key == null) {
            return $this->_vars;
        } else {
            if (isset($this->_vars[$key])) {
                return $this->_vars[$key];
            } else {
                return null;
            }
        }
    }

    public function removeParameter($key = null) {
        if ($key == null) {
            $this->_vars = array();
        } else {
            if (is_array($key)) {
                foreach($key as $index => $value) {
                    if (in_array($value, $this->_vars)) {
                        unset($this->_vars[$index]);
                    }
                }
            } else {
                if (in_array($key, $this->_vars)) {
                    unset($this->_vars[$index]);
                }
            }
        }
    }

    public function template_exists($file) {
        if (file_exists($this->_get_dir($this->template_dir).$file)) {
            return true;
        } else {
            return false;
        }
    }

    public function clear_cached($file = null, $cache_id = null) {
        if (!$this->cache) {
            return;
        }
        $this->_destroy_dir($file, $cache_id, $this->_get_dir($this->cache_dir));
    }

    public function is_cached($file, $cache_id = null) {
        if ($this->cache && $this->_is_cached($file, $cache_id)) {
            return true;
        } else {
            return false;
        }
    }

    public function display($file, $cache_id = null) {
        $this->fetch($file, $cache_id, true);
        return;
    }

    public function fetch($file, $cache_id = null, $display = false) {
        $this->_cache_id = $cache_id;
        $this->template_dir = $this->_get_dir($this->template_dir);
        if ($this->cache) {
            $this->_cache_dir = $this->_build_dir($this->cache_dir, $this->_cache_id);
        }
        $name = md5($this->template_dir.$file) . '.php';
        $this->_error_level = error_reporting(error_reporting() & ~E_NOTICE);

        if (!file_exists($this->template_dir.$file)) {
            $this->trigger_error("File '$file' does not exist.", E_USER_ERROR);
        }

        if ($this->cache && $this->is_cached($file, $cache_id)) {
            ob_start();
            include($this->_cache_dir.$name);
            $output = ob_get_contents();
            ob_end_clean();
            $output = substr($output, strpos($output, "\n") + 1); // remove the cache headers
        } else {
            // if we didn't get an xml file, we  will make our own empty one
            $xmldoc = new \DomDocument;
            if ($this->_xml == null) {
                $xmldoc->loadXML("<?xml version='1.0'?><tpl/>");
            } else {
                if ($this->_xml['type'] == 'file') {
                    $xmldoc->load($this->_xml['value']);
                }
                if ($this->_xml['type'] == 'string') {
                    $xmldoc->loadXML($this->_xml['value']);
                }
            }

            // create and load the xsl stylesheet/template
            $stylesheet = new \DomDocument;
            $stylesheet->load($this->template_dir.$file);

            // load the xsl processor and process our stylesheet
            $processor = new \XsltProcessor();
            $processor->registerPHPFunctions();
            $processor->importStylesheet($stylesheet);

            // go through our parameters and assign them to the template
            foreach ($this->_vars as $key => $value) {
                $processor->setParameter(null, $key, $value);
            }

            // get all included files
            $this->_cache_info = $this->_get_cache_info($this->template_dir . $file);

            if ($output = $processor->transformToXML($xmldoc)) {
                if ($this->cache) {
                    $f = fopen($this->_cache_dir.$name, "w");
                    fwrite($f, serialize($this->_cache_info) . "\n$output");
                    fclose($f);
                }
            } else {
                $this->trigger_error("XSL transformation failed.", E_USER_ERROR);
            }
        }

        error_reporting($this->_error_level);
        if ($display) {
            echo $output;
        } else {
            return $output;
        }
    }

    private function _get_cache_info($file) {
        if (!(file_exists($file) && ($contents = file_get_contents($file)))) {
            return array();
        }
        $matches = array();
        $files = array();
        preg_match_all('!\<xsl\:(?:import|include)\s+href=("[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\')\s*\/\>!', $contents, $matches);
        foreach ($matches[1] as $value) {
            $value = substr($value, 1, -1);
            if (!preg_match("/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/", $value)) {
                // path is relative
                $value = $this->template_dir . $value;
            } else {
                // path is absolute
                $value = str_replace('\\', '\\\\', $value);
            }
            $files[] = $value;
            array_merge($files, $this->_get_cache_info($value));
        }
        return $files;
    }

    private function _is_cached($file, $cache_id) {
        if (!$this->cache) {
            return false;
        }

        $this->_cache_dir = $this->_get_dir($this->cache_dir, $cache_id);
        $this->template_dir = $this->_get_dir($this->template_dir);
        $name = md5($this->template_dir.$file).'.php';

        if (file_exists($this->_cache_dir.$name) && (((time() - filemtime($this->_cache_dir.$name)) < $this->cache_lifetime) || $this->cache_lifetime == -1) && (filemtime($this->_cache_dir.$name) > filemtime($this->template_dir.$file))) {
            $fh = fopen($this->_cache_dir.$name, "r");
            if (!feof($fh) && ($line = fgets($fh, filesize($this->_cache_dir.$name)))) {
                $includes = unserialize($line);
                if (is_array($includes)) {
                    foreach($includes as $value) {
                        if (!(file_exists($value) && (filemtime($this->_cache_dir.$name) > filemtime($value)))) {
                            return false;
                        }
                    }
                }
            }
            fclose($fh);
        } else {
            return false;
        }
        return true;
    }

    private function _get_dir($dir, $id = null) {
        if (empty($dir)) {
            $dir = '.';
        }
        if (substr($dir, -1) != DIRECTORY_SEPARATOR) {
            $dir .= DIRECTORY_SEPARATOR;
        }
        if (!empty($id)) {
            $_args = explode('|', $id);
            if (count($_args) == 1 && empty($_args[0])) {
                return $dir;
            }
            foreach($_args as $value) {
                $dir .= $value.DIRECTORY_SEPARATOR;
            }
        }
        return $dir;
    }

    private function _build_dir($dir, $id) {
        $_args = explode('|', $id);
        if (count($_args) == 1 && empty($_args[0])) {
            return $this->_get_dir($dir);
        }
        $_result = $this->_get_dir($dir);
        foreach($_args as $value) {
            $_result .= $value.DIRECTORY_SEPARATOR;
            @mkdir($_result, 0777);
        }
        return $_result;
    }

    private function _destroy_dir($file, $id, $dir) {
        if ($file == null && $id == null) {
            if (is_dir($dir)) {
                if ($d = opendir($dir)) {
                    while(($f = readdir($d)) !== false) {
                        if ($f != '.' && $f != '..') {
                            $this->_rm_dir($dir.$f.DIRECTORY_SEPARATOR);
                        }
                    }
                }
            }
        } else {
            if ($id == null) {
                $this->template_dir = $this->_get_dir($this->template_dir);
                @unlink($dir.md5($this->template_dir.$file).'.php');
            } else {
                $_args = "";
                foreach(explode('|', $id) as $value) {
                    $_args .= $value.DIRECTORY_SEPARATOR;
                }
                $this->_rm_dir($dir.DIRECTORY_SEPARATOR.$_args);
            }
        }
    }

    private function _rm_dir($dir) {
        if (is_file(substr($dir, 0, -1))) {
            @unlink(substr($dir, 0, -1));
            return;
        }
        if ($d = opendir($dir)) {
            while(($f = readdir($d)) !== false) {
                if ($f != '.' && $f != '..') {
                    $this->_rm_dir($dir.$f.DIRECTORY_SEPARATOR);
                }
            }
            @rmdir($dir.$f);
        }
    }

    public function trigger_error($error_msg, $error_type = E_USER_ERROR) {
        trigger_error("[TPL] Template error: $error_msg", $error_type);
    }
}

?>
