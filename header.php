<?php

require dirname(__DIR__, 2) . '/mainfile.php';
if (!empty($xoopsUser)) {
    $_SERVER['PHP_AUTH_USER'] = $user;

    $_SERVER['PHP_AUTH_PW'] = $pass;

    $_SESSION['auth'] = true;
}
if ($SERVER_ADDR == $REMOTE_ADDR) {
    echo 'hello myAds';

    $_SERVER['PHP_AUTH_USER'] = 'myAds';

    $_SERVER['PHP_AUTH_PW'] = 'myAds';

    $_SESSION['auth'] = true;
}
