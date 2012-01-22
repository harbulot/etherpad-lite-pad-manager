<?php

function get_openid_providers_array() {
    return array(
        array(
            id => 'google',
            name => 'Google',
            label => null,
            url => 'https://www.google.com/accounts/o8/id',
        ),
        array(
            id => 'yahoo',
            name => 'Yahoo',
            label => null,
            url => 'http://me.yahoo.com/',
        ),
        array(
            id => 'openid',
            name => 'OpenID',
            label => 'Enter your OpenID:',
            url => null,
        ),
    );
}

function get_openid_providers_hash() {
    $hash = array();
    foreach (get_openid_providers_array() as $key => $value) {
        $hash[$value['id']] = $value;
    }
    return $hash;
}

function get_notepad_entries($ep, $group_id = null) {
    # get the list of notepads
    $pad_list = Array();
    try {
        $pad_list = $ep->listPads($group_id);
        $pad_list = $pad_list->padIDs;
    } catch (Exception $e) {}

    # build the list of notepads
    $entries = array();

    foreach ($pad_list as $pad => $key) {
        $pad_name_pieces = explode('$', $pad, 2);
        $pad_name = $pad_name_pieces[1];
        array_push($entries, $pad_name);
    }

    return $entries;
}

function get_notepad_entries_xml($ep, $group_id = null) {
    $xml = '';
    $notepads = get_notepad_entries($ep, $group_id);
    foreach ($notepads as $value) {
        $xml .= '<entry key="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</entry>';
    }
    return $xml;
}

function is_logged_in() {
    global $dbh;
    $logged_in_flag = 0;

    # remove any secrets that have expired
    $clear_sth = $dbh->prepare('DELETE FROM autologin WHERE expires < ?');
    $clear_sth->execute(array(time()));
    $clear_sth->closeCursor();

    # make a copy of the cookie
    $autologin = $_COOKIE['OPENID_AUTOLOGIN'];

    if (isset($autologin) && is_string($autologin)) {
        $pieces = unserialize(gzuncompress(base64_decode($autologin)));

        if (is_array($pieces)) {
            $openid = $pieces['username'];
            $secret = $pieces['secret'];

            $sth = $dbh->prepare('
                SELECT u.id, u.openid, u.nickname, u.is_manager, u.is_enabled
                FROM autologin a, users u
                WHERE a.user_id = u.id AND a.secret = ? AND u.openid = LOWER(?) AND u.is_enabled = 1
            ');
            $sth->execute(array($secret, $openid));
            if (list($user_id, $openid, $nickname, $is_manager, $is_enabled) = $sth->fetch()) {
                $_SESSION['authorized'] = 1;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['is_manager'] = $is_manager;
                $_SESSION['is_enabled'] = $is_enabled;
                $_SESSION['identity'] = $openid;

                if (!$nickname) { $nickname = generate_nickname($user_id); }
                $_SESSION['nickname'] = $nickname;

                $logged_in_flag = 1;
            }
            $sth->closeCursor();
        }
    }

    # if the session is authorized then log the person in
    if ($_SESSION['authorized']) {
        $logged_in_flag = 1;
    }

    return $logged_in_flag;
}

function generate_nickname($user_id) {
    global $dbh;

    $nickname = uniqid('user', false);

    $sth = $dbh->prepare('UPDATE users SET nickname = ? WHERE id = ?');
    $sth->execute(array($nickname, $user_id));
    $sth->closeCursor();

    return $nickname;
}

?>
