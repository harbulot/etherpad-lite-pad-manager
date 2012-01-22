<?php
namespace org\lockaby;

class configuration {
    private $_values = array();

    public function loadConfiguration($file) {
        if (!$_SERVER['CONFIGURATION']) {
            die("Environment variable CONFIGURATION not set.");
        }

        # if we weren't given a file to load, we load our predefined one
        if (!isset($file)) {
            $file = $_SERVER['CONFIGURATION'] . "/default";
        } else {
            $file = $_SERVER['CONFIGURATION'] . "/" . $file;
        }

        if (!is_readable($file)) {
            die("Could not open configuration file " . $file . ".");
        }

        $handle = fopen($file, "r");
        while ($line = fgets($handle)) {
            $line = trim($line);
            $line = preg_replace('/^[#;].*/', '', $line);
            if (!strlen($line)) { continue; }

            $pieces = preg_split('/\s*=\s*/', $line, 2);
            if (!isset($pieces[1]) || !strlen($pieces[1])) { continue; }

            $this->_values[$pieces[0]] = $pieces[1];
        }
        fclose($handle);

        return $this->_values;
    }

    public function getConfigurationValues() {
        return $this->_values;
    }

    public function getConfigurationValue($key) {
        return $this->_values[$key];
    }
}

?>
