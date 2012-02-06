<?php
# our standard includes
require_once('includes/php-lib/org/lockaby/class.configuration.php');
require_once('includes/php-lib/org/lockaby/class.db.php');
require_once('includes/php-lib/org/lockaby/class.session.php');

# libraries for this application
require_once('includes/support.php');

date_default_timezone_set("UTC");

# load configuration options
$config = new org\lockaby\configuration;
$values = $config->loadConfiguration("default");

# connect to the database with PDO for normal things
$db = new org\lockaby\db;
$dbh = $db->connect($values['db_database'], $values['db_username'], $values['db_password']);

$session = new org\lockaby\session($dbh);
$session->setName('NOTEPAD_SESSION');
$session->setPath('/');
$session->start();
//session_start();

if (is_logged_in()) {
    # make a copy of the cookie
    $autologin = $_COOKIE['OPENID_AUTOLOGIN'];

    if (isset($autologin) && is_string($autologin)) {
        $pieces = unserialize(gzuncompress(base64_decode($autologin)));

        if (is_array($pieces)) {
            $openid = $pieces['username'];
            $secret = $pieces['secret'];

            $sth = $dbh->prepare('DELETE FROM autologin WHERE secret = ? AND user_id = ?');
            $sth->execute(array($secret, $_SESSION['user_id']));
            $sth->closeCursor();
        }
    }

    # clear any autologin cookie that may exist
    setcookie('OPENID_AUTOLOGIN', '', time() - 3600, '/', null, true, true);

    # destroy this user session
    session_destroy();
}

header('Location: index.php');
exit;

?>
