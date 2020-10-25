<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
require __DIR__ . '/header.php';
require XOOPS_ROOT_PATH . '/header.php';
$content = 4;
$title = 'Newsgroup';
// Import the NNTP Utility
require 'webnews/nntp.php';
require 'config/config.php';
// Read the messages file
$messages_ini = read_ini_file($text_ini, true);
// Start the session before output anything
session_name($session_name);
session_start();
// Perform logout
if (is_requested('logout')) {
    $user = '';

    $pass = '';

    unset($_SESSION['auth']);

    $_SESSION['logout'] = true;

    header('Location: ' . construct_url($logout_url));

    exit;
} elseif (isset($_SESSION['auth']) && $_SESSION['auth']) {
    if (!empty($xoopsUser)) { //this is so I can auto-post classified ads
        $_SERVER['PHP_AUTH_USER'] = $xoopsUser->getVar('uname', 'E');

        $_SERVER['PHP_AUTH_PW'] = $xoopsUser->getVar('pass', 'E');
    }
}
if ($auth_level > 1) {
    if ((3 == $auth_level) || (is_requested('compose') && (2 == $auth_level))) {
        // Do HTTP Basic authentication

        if ($_SESSION['logout'] || !isset($_SERVER['PHP_AUTH_USER'])) {
            unset($_SESSION['logout']);

            header('WWW-Authenticate: Basic realm="' . $realm . '"');

            header('HTTP/1.0 401 Unauthorized');

            echo $messages_ini['authorization']['login'];

            exit;
        }  

        // $_SESSION["auth"] must be checked first to avoid making too many connections

        //if ($_SESSION["auth"] || verify_login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {

        if ($_SESSION['auth'] || !empty($xoopsUser)) {
            if (!empty($xoopsUser)) { //this is so I can auto-post classified ads
                $_SERVER['PHP_AUTH_USER'] = $xoopsUser->getVar('uname', 'E');

                $_SERVER['PHP_AUTH_PW'] = $xoopsUser->getVar('pass', 'E');
            }

            $_SESSION['auth'] = true;
        } else {
            header('WWW-Authenticate: Basic realm="' . $realm . '"');

            header('HTTP/1.0 401 Unauthorized');

            echo $messages_ini['authorization']['login'];

            exit;
        }

        // Authentication done
    }
} else {
    $_SESSION['auth'] = true;
}
// Create the NNTP object
$nntp = new NNTP($nntp_server, $user, $pass, $proxy_server, $proxy_port, $proxy_user, $proxy_pass);
// Load the newsgroups_list
$connected = false;
if (!isset($_SESSION['newsgroups_list'])) { // Need to update the newsgroups_list first
    $_SESSION['newsgroups_list'] = [];

    foreach ($newsgroups_list as $group) {
        if (false !== mb_strpos($group, '*')) { // Group name have wildmat, expand it.
            if (!$nntp->connect()) {
                $nntp->quit();

                unset($_SESSION['newsgroups_list']);

                echo '<b>' . $messages_ini['error']['nntp_fail'] . '</b><br>';

                echo $nntp->get_error_message() . '<br>';

                exit;
            }

            $connected = true;

            $group_list = $nntp->get_group_list($group);

            if (false !== $group_list) {
                sort($group_list);

                $_SESSION['newsgroups_list'] = array_merge($_SESSION['newsgroups_list'], $group_list);
            }
        } else {
            $_SESSION['newsgroups_list'][] = $group;
        }
    }
}
if ($connected) {
    $nntp->quit();
}
$newsgroups_list = $_SESSION['newsgroups_list'];
if (is_requested('cancel')) {
    // Back to show header

    $renew = 0;

    $content_page = 'webnews/show_header.php';
} elseif (is_requested('attachment_id') && is_requested('message_id')) {
    $attachment_id = get_request('attachment_id');

    $message_id = get_request('message_id');

    $content_page = 'webnews/attachmentHandler.php';
} elseif (is_requested('compose')) {
    $compose = get_request('compose');

    if (0 == strcasecmp($compose, 'post')) {
        // Do add_file or post

        $content_page = 'webnews/post_message.php';
    } else {
        $content_page = 'webnews/compose_message.php';
    }
} elseif (is_requested('preferences')) {
    $content_page = 'webnews/preferences.php';
} else {
    if (is_requested('group')) {
        $group = get_request('group');
    } else {
        unset($group);
    }

    if (is_requested('refresh')) {
        $refresh = true;

        unset($group);
    }

    if (!isset($_SESSION['newsgroup'])) {
        if (!isset($group) || !in_array($group, $newsgroups_list, true)) {
            $_SESSION['newsgroup'] = $default_group;
        } else {
            $_SESSION['newsgroup'] = $group;
        }
    } elseif (isset($group)) {
        if (isset($_SESSION['newsgroup'])) {
            if ((0 == strcmp($_SESSION['newsgroup'], $group)) || !in_array($group, $newsgroups_list, true)) {
                $renew = 0;
            } else {
                $_SESSION['newsgroup'] = $group;

                $refresh = true;
            }
        }
    }

    if (is_requested('rss_feed')) {
        $content_page = 'webnews/rss.php';
    } elseif (is_requested('article_id')) {
        $article_id = get_request('article_id');

        $content_page = 'webnews/show_article.php';
    } else {
        $content_page = 'webnews/show_header.php';

        if (is_requested('renew')) {
            $renew = get_request('renew');
        } else {
            unset($renew);
        }

        if (!isset($renew)) {
            $renew = 1;
        } else {
            $renew = (0 == strcasecmp($renew, 'true')) ? 1 : 0;
        }

        $need_expand = (1 == $renew) ? true : false;

        if (is_requested('expand')) {
            $_SESSION['expand_all'] = true;

            $need_expand = true;

            $renew = 0;
        } elseif (is_requested('collapse')) {
            $_SESSION['expand_all'] = false;

            $need_expand = true;

            $renew = 0;
        } elseif (!isset($_SESSION['expand_all'])) {
            $_SESSION['expand_all'] = $default_expanded;
        }

        if (is_requested('mid')) {
            $mid = get_request('mid');

            $on_load_script = "location = '#" . $mid . "';";
        }
    }
}
include $template;
require XOOPS_ROOT_PATH . '/footer.php';
