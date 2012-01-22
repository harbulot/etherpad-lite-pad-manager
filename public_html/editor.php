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

$session = new org\lockaby\session($dbh, '/notepad');
session_start();

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

# connect to the etherpad API
$ep = new org\lockaby\etherpad\client($values['apikey'], $values['apiurl']);

$public_group_key = $values['public_group_key'];
$private_group_key = 'private' . $_SESSION['user_id'];

# create pad groups
try {
    $public_group = $ep->createGroupIfNotExistsFor($public_group_key);
    $public_group_id = $public_group->groupID;
    $private_group = $ep->createGroupIfNotExistsFor($private_group_key);
    $private_group_id = $private_group->groupID;
} catch (Exception $e) {}

$action = $_GET['action'];
if ($action) {
    print '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    print '<response>';
        try {
            if ($action == 'open') {
                $pad = $_GET['pad'];
                $group = $_GET['group'];
                $group_id = "";
                if ($group === 'public') { $group_id = $public_group_id; }
                if ($group === 'private') { $group_id = $private_group_id; }

                try {
                    # this might fail if the pad already exists but that is ok
                    $ep->createGroupPad($group_id, $pad, "");
                } catch (Exception $e) {}
                $ep->setPublicStatus($group_id . '$' . $pad, "true");

                print '<content>';
                    print '<entry>' . htmlspecialchars($group_id . '$' . $pad) . '</entry>';
                    print '<entries>';
                        print '<public>';
                            print get_notepad_entries_xml($ep, $public_group_id);
                        print '</public>';
                        print '<private>';
                            print get_notepad_entries_xml($ep, $private_group_id);
                        print '</private>';
                    print '</entries>';
                print '</content>';
            }
            if ($action == 'delete') {
                $pad = $_GET['pad'];
                $group = $_GET['group'];
                $group_id = "";
                if ($group === 'public') { $group_id = $public_group_id; }
                if ($group === 'private') { $group_id = $private_group_id; }
                $ep->deletePad($group_id . '$' . $pad);

                print '<content>';
                    print '<result>success</result>';
                    print '<entries>';
                        print '<public>';
                            print get_notepad_entries_xml($ep, $public_group_id);
                        print '</public>';
                        print '<private>';
                            print get_notepad_entries_xml($ep, $private_group_id);
                        print '</private>';
                    print '</entries>';
                print '</content>';
            }
            if ($action == 'profile') {
                $username = $_GET['username'];
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
                    print '<entries>';
                        print '<public>';
                            print get_notepad_entries_xml($ep, $public_group_id);
                        print '</public>';
                        print '<private>';
                            print get_notepad_entries_xml($ep, $private_group_id);
                        print '</private>';
                    print '</entries>';
                print '</content>';
            }
            if ($action == 'users') {
                if ($_SESSION['is_manager']) {
                    print '<content>';
                        print '<result>success</result>';
                        print '<users>';
                            $sth = $dbh->prepare('SELECT id, openid, is_enabled, is_manager FROM users ORDER BY openid ASC');
                            $sth->execute();
                            while (list($id, $openid, $is_enabled, $is_manager) = $sth->fetch()) {
                                print '<user enabled="' . $is_enabled . '" manager="' . $is_manager . '"><![CDATA[' . $openid . ']]></user>';
                            }
                            $sth->closeCursor();
                        print '</users>';
                    print '</content>';
                }
            }
            if ($action == 'toggle') {
                $username = $_GET['username'];
                $field = $_GET['field'];
                $value = $_GET['value'];
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
    $xml .= '<entries>';
        $xml .= '<public>' . get_notepad_entries_xml($ep, $public_group_id) . '</public>';
        $xml .= '<private>' . get_notepad_entries_xml($ep, $private_group_id) . '</private>';
    $xml .= '</entries>';
$xml .= '</template>';

$tpl->setParameter('title', 'notepad');
$tpl->setCacheLocation('includes/cached/');
$tpl->setTemplateLocation('includes/templates/');
$tpl->loadXMLFromString($xml);
$tpl->display("page_notepad.xsl", "index");

?>
