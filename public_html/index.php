<?php
# our standard includes
require_once('includes/php-lib/org/lockaby/class.configuration.php');
require_once('includes/php-lib/org/lockaby/class.db.php');
require_once('includes/php-lib/org/lockaby/class.utilities.php');
require_once('includes/php-lib/org/lockaby/class.template.php');
require_once('includes/php-lib/org/lockaby/class.session.php');
require_once('includes/php-lib/org/lockaby/class.openid.php');
require_once('includes/php-lib/org/lockaby/etherpad/class.client.php');

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

# check to see if there is an autologin cookie here
# if autologin exists then redirect to editor.php
if (is_logged_in()) {
    header('Location: editor.php');
    exit;
}

$errors = array();

try {
    $openid = new org\lockaby\openid($values['url']);
    $openid->realm = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';

    # the url we will redirect to, if any
    $redirect = null;

    if (!$openid->mode) {
        if (isset($_POST['provider'])) {
            # get the provider, the url, and the username
            $providers = get_openid_providers_hash();
            $provider = stripslashes($_POST['provider']);
            $id = $providers[$provider]['url'];
            if (!$id) { $id = stripslashes($_POST['username']); }
            $openid->identity = $id;

            # we will use this later
            $_SESSION['remember'] = isset($_POST['remember']);

            $redirect = $openid->authUrl();
        }
    } elseif ($openid->mode == 'cancel') {
        throw new Exception('You cancelled your login.');
    } else {
        if ($openid->validate()) {
            $username = $openid->identity;

            # get the user's user id
            $get_id_sth = $dbh->prepare('SELECT id, nickname, is_manager, is_enabled FROM users WHERE openid = LOWER(?)');
            $get_id_sth->execute(array($username));
            list ($user_id, $nickname, $is_manager, $is_enabled) = $get_id_sth->fetch();
            $get_id_sth->closeCursor();

            if (!$user_id) {
                $create_user_sth = $dbh->prepare('INSERT INTO users (openid, created, is_manager, is_enabled) VALUES (LOWER(?), NOW(), 0, 0)');
                $create_user_sth->execute(array($username));
                $create_user_sth->closeCursor();
                throw new Exception("Your account has been registered but is not yet enabled. Please ask the administrator to enable your access before logging in again.");
            }
            if (!$is_enabled) {
                throw new Exception("Your account has been registered but is not yet enabled. Please ask the administrator to enable your access before logging in again.");
            }

            # record that we logged in
            $log_user_sth = $dbh->prepare('UPDATE users SET logged = NOW() WHERE id = ?');
            $log_user_sth->execute(array($user_id));
            $log_user_sth->closeCursor();

            # record that we made an action
            $log_action_sth = $dbh->prepare('INSERT INTO log (user_id, logged, ip_address, useragent) VALUES (?, NOW(), ?, ?)');
            $log_action_sth->execute(array($user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']));
            $log_action_sth->closeCursor();

            $_SESSION['authorized'] = 1;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['is_enabled'] = $is_enabled;
            $_SESSION['is_manager'] = $is_manager;
            $_SESSION['identity'] = $username;

            if (!$nickname) { $nickname = generate_nickname($user_id); }
            $_SESSION['nickname'] = $nickname;

            if ($_SESSION['remember']) {
                # generate a login ID for this user
                # used to verify that the user is returning, regenerated every time the user logs in
                $secret = org\lockaby\utilities::get_md5_from_string(
                    org\lockaby\utilities::get_md5_from_string(
                        time() . rand(0, pow(2, 48)) . posix_getpid() . $_SERVER['REMOTE_ADDR']
                    )
                );
                $expires = 2592000; # 30 days

                # save the ID in the database
                # there can be multiple ids for each user because a user could have saved on multiple computers
                $save_secret_sth = $dbh->prepare('INSERT INTO autologin (user_id, secret, expires) VALUES (?, ?, ?)');
                $save_secret_sth->execute(array($user_id, $secret, time() + $expires));
                $save_secret_sth->closeCursor();

                setcookie('OPENID_AUTOLOGIN', base64_encode(gzcompress(serialize(array(secret => $secret, username => $username)))), time() + $expires, '/', null, true, true);
            }

            $redirect = "editor.php";
        } else {
            # nopers
            throw new Exception('You were not logged in.');
        }
    }

    if ($redirect !== null) {
        header('Location: ' . $redirect);
        exit;
    }
} catch (Exception $e) {
    array_push($errors, '<error>' . stripslashes($e->getMessage()) . '</error>');
}

# now display a login form here
$tpl = new org\lockaby\template;

$xml = '<template>';
    $xml .= '<errors>' . implode('', $errors) . '</errors>';
    $xml .= '<openid>';
        $xml .= '<providers>';
            foreach (get_openid_providers_array() as $key => $value) {
                $xml .= '<provider id="' . $value['id'] . '">';
                    $xml .= '<name>' . htmlspecialchars($value['name']) . '</name>';
                    $xml .= '<label>' . htmlspecialchars($value['label']) . '</label>';
                    $xml .= '<url>' . htmlspecialchars($value['url']) . '</url>';
                $xml .= '</provider>';
            }
        $xml .= '</providers>';
    $xml .= '</openid>';
$xml .= '</template>';

$tpl->setParameter('title', 'notepad login');
$tpl->setParameter('providers', str_replace('\/', '/', json_encode(get_openid_providers_hash())));
$tpl->setCacheLocation('includes/cached/');
$tpl->setTemplateLocation('includes/templates/');
$tpl->loadXMLFromString($xml);
$tpl->display("page_login.xsl", "index");

?>
