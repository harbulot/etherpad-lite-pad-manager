<?php
namespace org\lockaby;

class utilities {
    public static function get_md5_from_string($string) {
        return md5($string);
    }

    public static function check_password($username, $password) {
        $d = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        );
        $h = proc_open("/usr/local/apache2/bin/pwauth", $d, $pipes);
        if (is_resource($h)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout

            fwrite($pipes[0], $username . "\n");
            fwrite($pipes[0], $password . "\n");
            fclose($pipes[0]);

            #echo stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            return proc_close($h);
        } else {
            trigger_error("Unable to run pwauth program.", E_USER_ERROR);
        }
    }
}

?>
