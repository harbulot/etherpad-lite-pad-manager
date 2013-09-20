<?php
namespace org\lockaby;

class session {
    protected $_dbh = null;
    protected $_connected = false;
    protected $_gc_maxlifetime;
    protected $_lifetime;

    protected $_sql_destroy = 'DELETE FROM sessions WHERE id = ?';

    protected $name = null;
    protected $path = null;
    protected $domain = null;

    public function __construct($p1 = null, $p2 = null, $p3 = null) {
        // we were passed a PDO object so we are going to use that
        if ($p1 && gettype($p1) == 'object' && get_class($p1) == 'PDO') {
            $this->_dbh = $p1;
        } elseif ($p1 && gettype($p1) == 'string' && $p2 && gettype($p2) == 'string' && $p3 && gettype($p3) == 'string') {
            try {
                $dbh = new \PDO('pgsql:dbname=' . $p1.';host=localhost', $p2, $p3, array(\PDO::ATTR_PERSISTENT => true));

                // we're doing this dumb kludge because PHP PDO doesn't support buffered queries by default
                // (by the way, perl does, why the fuck am i using php?)
                $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $dbh->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
                $dbh->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);

                $this->_dbh = $dbh;
            } catch (PDOException $e) {
                die("Database connection error: " . $e->getMessage());
            }
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

        return;
    }

    public function start() {
        $dbh = $this->_dbh;
        $context = $this;

        $handler = session_set_save_handler(
            function ()
                use (&$context) {

                # open
                return $context->getConnected();
            },
            function ()
                use (&$context) {

                # close
                return $context->getConnected();
            },
            function ($id)
                use (&$context, $dbh) {

                # read
                $data = null;

                try {
                    $sth = $dbh->prepare('SELECT data FROM sessions WHERE id = ? AND last_access >= ?');
                    $sth->execute(array($id, time() - $context->getGarbageMaxLifeTime()));
                    list ($data) = $sth->fetch();
                    $sth->closeCursor();
                } catch (Exception $e) {
                    die("Session read error: " . $e->getMessage());
                }

                return $data;
            },
            function ($id, $data)
                use (&$context, $dbh) {

                # write
                try {
                    $sth = $dbh->prepare('DELETE FROM sessions WHERE id=?');
                    $sth->execute(array($id));
                    $sth = $dbh->prepare('INSERT INTO sessions (id, data, last_access) VALUES (?, ?, ?)');
                    $sth->execute(array($id, $data, time()));
                    $sth->closeCursor();
                } catch (Exception $e) {
                    die("Session write error: " . $e->getMessage());
                    return false;
                }

                return true;
            },
            function ($id)
                use (&$context, $dbh) {

                # destroy
                try {
                    $sth = $dbh->prepare('DELETE FROM sessions WHERE id = ?');
                    $sth->execute(array($id));
                    $sth->closeCursor();
                } catch (Exception $e) {
                    die("Session destruction error: " . $e->getMessage());
                    return false;
                }

                return true;
            },
            function ($maxlifetime)
                use (&$context, $dbh) {

                # gc
                try {
                    $sth = $dbh->prepare('DELETE FROM sessions WHERE last_access < ?');
                    $sth->execute(array(time() - $maxlifetime));
                    $sth->closeCursor();
                } catch (Exception $e) {
                    die("Session garbage collection error: " . $e->getMessage());
                    return false;
                }

                return true;
            }
        );
        if (!$handler) {
            die("Could not create custom session handler.");
        }

        // set a default lifetime of when the browser closes and allow a custom path and domain
        // set secure = false and httponly = false
        session_set_cookie_params($this->_lifetime, $this->getPath(), $this->getDomain(), false, false);

        // set a name instead of PHPSESSID
        session_name($this->getName());

        $this->_connected = session_start();
        return $this->_connected;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setPath($path) {
        $this->path = $path;
    }

    public function setDomain($domain) {
        $this->domain = $domain;
    }

    public function getName() {
        return $this->name;
    }

    public function getPath() {
        return $this->path;
    }

    public function getDomain() {
        return $this->domain;
    }

    public function getConnected() {
        return $this->_connected;
    }

    public function getGarbageMaxLifeTime() {
        return $this->_gc_maxlifetime;
    }
}

?>
