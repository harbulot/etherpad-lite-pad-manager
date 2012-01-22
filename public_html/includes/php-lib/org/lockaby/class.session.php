<?php
namespace org\lockaby;

class session {
    protected $_dbh = null;
    protected $_connected = false;
    protected $_gc_maxlifetime;
    protected $_lifetime;

    protected $_sql_gc = 'DELETE FROM sessions WHERE last_access < ?';
    protected $_sql_read = 'SELECT data FROM sessions WHERE id = ? AND last_access >= ?';
    protected $_sql_write = 'REPLACE INTO sessions (id, data, last_access) VALUES (?, ?, ?)';
    protected $_sql_destroy = 'DELETE FROM sessions WHERE id = ?';

    public function __construct($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) {
        $path = null;
        $domain = null;

        // we were passed a PDO object so we are going to use that
        if ($p1 && gettype($p1) == 'object' && get_class($p1) == 'PDO') {
            $path = $p2;
            $domain = $p3;
            $this->_dbh = $p1;
        } elseif ($p1 && gettype($p1) == 'string' && $p2 && gettype($p2) == 'string' && $p3 && gettype($p3) == 'string') {
            try {
                $dbh = new \PDO('mysql:dbname=' . $p1, $p2, $p3, array(\PDO::ATTR_PERSISTENT => true));

                // we're doing this dumb kludge because PHP PDO doesn't support buffered queries by default
                // (by the way, perl does, why the fuck am i using php?)
                $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $dbh->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
                $dbh->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);

                $this->_dbh = $dbh;
            } catch (PDOException $e) {
                die("Database connection error: " . $e->getMessage());
            }

            $path = $p4;
            $domain = $p5;
        } else {
            throw new \Exception("Invalid parameters given to create a session object. Pass either a PDO handle or a username/password/database name.");
        }

        $this->_gc_maxlifetime = ini_get('session.gc_maxlifetime');

        // Sessions last for a day unless otherwise specified. 
        if (!$this->_gc_maxlifetime) {
            $this->_gc_maxlifetime = 86400;
        }

        $this->_lifetime = ini_get('session.cookie_lifetime');
        if (!$this->_lifetime) {
            $this->_lifetime = 0;
        }

        // set a default lifetime of when the browser closes and allow a custom path and domain
        // set secure = false and httponly = true
        session_set_cookie_params($this->_lifetime, $path, $domain, false, true);

        if (!session_set_save_handler(array(&$this,'_open'),
                                      array(&$this,'_close'),
                                      array(&$this,'_read'),
                                      array(&$this,'_write'),
                                      array(&$this,'_destroy'),
                                      array(&$this,'_gc'))) {
            throw new \Exception("Session error. session_set_save_handler() failed.");
        }

        $this->_connected = true;
        return $this->_connected;
    }

    function _open() {
        return $this->_connected;
    }

    function _close() {
        return $this->_connected;
    }

    function _read($id) {
        if (!$this->_connected) { return false; }

        $data = null;

        try {
            $sth = $this->_dbh->prepare($this->_sql_read);
            $sth->execute(array($id, time() - $this->_gc_maxlifetime));
            list ($data) = $sth->fetch();
            $sth->closeCursor();
        } catch (Exception $e) {
            die("Session read error: " . $e->getMessage());
        }

        return $data;
    }

    function _write($id, $data) {
        try {
            $sth = $this->_dbh->prepare($this->_sql_write);
            $sth->execute(array($id, $data, time()));
            $sth->closeCursor();
        } catch (Exception $e) {
            die("Session write error: " . $e->getMessage());
            return false;
        }

        return true;
    }

    function _destroy($id) {
        try {
            $sth = $this->_dbh->prepare($this->_sql_destroy);
            $sth->execute(array($id));
            $sth->closeCursor();
        } catch (Exception $e) {
            die("Session destruction error: " . $e->getMessage());
            return false;
        }

        return true;
    }

    function _gc($maxlifetime) {
        try {
            $sth = $this->_dbh->prepare($this->_sql_gc);
            $sth->execute(array(time() - $maxlifetime));
            $sth->closeCursor();
        } catch (Exception $e) {
            die("Session garbage collection error: " . $e->getMessage());
            return false;
        }

        return true;
    }
}

?>
