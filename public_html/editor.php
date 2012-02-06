<?php
# our standard includes
require_once('includes/php-lib/org/lockaby/class.configuration.php');
require_once('includes/php-lib/org/lockaby/class.db.php');
require_once('includes/php-lib/org/lockaby/class.template.php');
require_once('includes/php-lib/org/lockaby/class.session.php');
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

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

# connect to the etherpad API
$ep = new org\lockaby\etherpad\client($values['apikey'], $values['apiurl']);

$action = stripslashes($_GET['action']);
if ($action) {
    print '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    print '<response>';
        try {
            if ($action == 'open') {
                $id = stripslashes($_GET['id']);
                $name = stripslashes($_GET['name']);
                $is_private = stripslashes($_GET['private']);

                if (!$id || $id === 'null') {
                    # if no id is given then this is a new notepad
                    # make sure that this notepad has a name
                    if (!$name) {
                        throw new Exception('No name given. Cannot create new notepad without a name.');
                    }

                    # generate a new id
                    $id = uniqid('notepad', true);

                    # store the name of the pad
                    $sth = $dbh->prepare('INSERT INTO pads (id, name, is_private, user_id) VALUES (?, ?, ?, ?)');
                    $sth->execute(array($id, $name, ($is_private === 'true' ? 1 : 0), $_SESSION['user_id']));
                    $sth->closeCursor();
                } else {
                    # make sure that this user can actually see the pad
                    $sth = $dbh->prepare('SELECT COUNT(*) FROM pads WHERE id = ? AND (user_id = ? OR is_private = 0)');
                    $sth->execute(array($id, $_SESSION['user_id']));
                    list ($count) = $sth->fetch();
                    $sth->closeCursor();

                    if (!$count) {
                        throw new Exception('No notepad found. Cannot open this notepad.');
                    }
                }

                # create the pad in etherpad
                try {
                    # this might fail if the pad already exists but that is ok
                    $ep->createPad($id, "");
                } catch (Exception $e) {}

                print '<content>';
                    print '<notepad id="' . htmlspecialchars($id) . '" private="' . (($is_private === 'true') ? 'true' : 'false') . '">';
                        print '<![CDATA[' . $name . ']]>';
                    print '</notepad>';
                    print '<notepads>';
                        print '<public>';
                            print get_public_notepads_xml();
                        print '</public>';
                        print '<private>';
                            print get_private_notepads_xml();
                        print '</private>';
                    print '</notepads>';
                print '</content>';
            }
            if ($action == 'rename') {
                $id = stripslashes($_GET['id']);
                $name = stripslashes($_GET['name']);
                $is_private = stripslashes($_GET['private']);

                # make sure that this user can actually see the pad
                $sth = $dbh->prepare('SELECT COUNT(*) FROM pads WHERE id = ? AND user_id = ?');
                $sth->execute(array($id, $_SESSION['user_id']));
                list ($count) = $sth->fetch();
                $sth->closeCursor();

                if (!$count) {
                    throw new Exception('No notepad found or you do not have permission to rename this notepad. Cannot rename this notepad.');
                } else {
                    $sth = $dbh->prepare('UPDATE pads SET name = ?, is_private = ? WHERE id = ?');
                    $sth->execute(array($name, ($is_private === 'true' ? 1 : 0), $id));
                    $sth->closeCursor();
                }

                print '<content>';
                    print '<notepad id="' . htmlspecialchars($id) . '" private="' . (($is_private === 'true') ? 'true' : 'false') . '">';
                        print '<![CDATA[' . $name . ']]>';
                    print '</notepad>';
                    print '<notepads>';
                        print '<public>';
                            print get_public_notepads_xml();
                        print '</public>';
                        print '<private>';
                            print get_private_notepads_xml();
                        print '</private>';
                    print '</notepads>';
                print '</content>';
            }
            if ($action == 'destroy') {
                $id = stripslashes($_GET['id']);

                # make sure that this user can actually see the pad
                $sth = $dbh->prepare('SELECT COUNT(*) FROM pads WHERE id = ? AND user_id = ?');
                $sth->execute(array($id, $_SESSION['user_id']));
                list ($count) = $sth->fetch();
                $sth->closeCursor();

                if (!$count) {
                    throw new Exception('No notepad found or you do not have permission to delete this notepad. Cannot delete this notepad.');
                } else {
                    # first delete from the database
                    $sth = $dbh->prepare('DELETE FROM pads WHERE id = ?');
                    $sth->execute(array($id));
                    $sth->closeCursor();

                    # now delete it from the store
                    $ep->deletePad($id);
                }

                print '<content>';
                    print '<result>success</result>';
                    print '<notepads>';
                        print '<public>';
                            print get_public_notepads_xml();
                        print '</public>';
                        print '<private>';
                            print get_private_notepads_xml();
                        print '</private>';
                    print '</notepads>';
                print '</content>';
            }
            if ($action == 'profile') {
                $username = stripslashes($_GET['username']);
                $sth = $dbh->prepare('UPDATE users SET nickname = ? WHERE id = ?');
                $sth->execute(array($username, $_SESSION['user_id']));
                $sth->closeCursor();

                print '<content>';
                    print '<result>success</result>';
                print '</content>';
            }
            if ($action == 'update') {
                print '<content>';
                    print '<result>success</result>';
                    print '<notepads>';
                        print '<public>';
                            print get_public_notepads_xml();
                        print '</public>';
                        print '<private>';
                            print get_private_notepads_xml();
                        print '</private>';
                    print '</notepads>';
                print '</content>';
            }
            if ($action == 'users') {
                if ($_SESSION['is_manager']) {
                    print '<content>';
                        print '<result>success</result>';
                        print '<users>';
                            $sth = $dbh->prepare('SELECT id, openid, nickname, is_enabled, is_manager FROM users ORDER BY openid ASC');
                            $sth->execute();
                            while (list($id, $openid, $nickname, $is_enabled, $is_manager) = $sth->fetch()) {
                                print '<user enabled="' . $is_enabled . '" manager="' . $is_manager . '">';
                                    print '<username><![CDATA[' . $openid . ']]></username>';
                                    print '<nickname><![CDATA[' . $nickname . ']]></nickname>';
                                print '</user>';
                            }
                            $sth->closeCursor();
                        print '</users>';
                    print '</content>';
                }
            }
            if ($action == 'toggle') {
                $username = stripslashes($_GET['username']);
                $field = stripslashes($_GET['field']);
                $value = stripslashes($_GET['value']);
                if ($_SESSION['is_manager']) {
                    if ($field == 'is_manager') {
                        $sth = $dbh->prepare('UPDATE users SET is_manager = ? WHERE openid = LOWER(?)');
                        if ($value) {
                            $sth->execute(array(1, $username));
                        } else {
                            $sth->execute(array(0, $username));
                        }
                        $sth->closeCursor();
                    }
                    if ($field == 'is_enabled') {
                        $sth = $dbh->prepare('UPDATE users SET is_enabled = ? WHERE openid = LOWER(?)');
                        if ($value) {
                            $sth->execute(array(1, $username));
                        } else {
                            $sth->execute(array(0, $username));
                        }
                        $sth->closeCursor();
                    }
                    print '<content>';
                        print '<result>success</result>';
                    print '</content>';
                }
            }
        } catch (Exception $e) {
            print '<errors><error>' . htmlspecialchars($e->getMessage()) . '</error></errors>';
        }
    print '</response>';
    exit;
}

$tpl = new org\lockaby\template;

$xml = '<template>';
    $xml .= '<editor>';
        $xml .= '<url><![CDATA[' . (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $values['pad'] . ']]></url>';
        $xml .= '<user enabled="' . $_SESSION['is_enabled'] . '" manager="' . $_SESSION['is_manager'] . '">';
            $xml .= '<nickname>' . htmlspecialchars($_SESSION['nickname']) . '</nickname>';
            $xml .= '<openid>' . $_SESSION['identity'] . '</openid>';
        $xml .= '</user>';
    $xml .= '</editor>';
    $xml .= '<notepads>';
        $xml .= '<public>' . get_public_notepads_xml() . '</public>';
        $xml .= '<private>' . get_private_notepads_xml() . '</private>';
    $xml .= '</notepads>';
$xml .= '</template>';

$tpl->setParameter('title', 'notepad');
$tpl->setCacheLocation('includes/cached/');
$tpl->setTemplateLocation('includes/templates/');
$tpl->loadXMLFromString($xml);
$tpl->display("page_notepad.xsl", "index");

?>
