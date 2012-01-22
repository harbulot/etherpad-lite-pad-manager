<?php
namespace org\lockaby;

class db {
    private $_dbh = null;

    public function connect($database, $username, $password, $path = null, $domain = null) {
        try {
            $this->_dbh = new \PDO('mysql:dbname=' . $database, $username, $password, array(\PDO::ATTR_PERSISTENT => true));

            // we're doing this dumb kludge because PHP PDO doesn't support buffered queries by default
            // (by the way, perl does, why the fuck am i using php?)
            $this->_dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->_dbh->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
            $this->_dbh->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        } catch (PDOException $e) {
            die("Database connection error: " . $e->getMessage());
        }

        return $this->_dbh;
    }
}

?>
