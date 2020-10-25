<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
unset($_SESSION['attach_count']);
if ((0 == strcasecmp($compose, 'reply')) && is_requested('mid')) {
    // $nntp = new NNTP($nntp_server, $user, $pass);

    $reply_id = get_request('mid');

    if (!$nntp->connect()) {
        echo "<font face=\"$font_family\"><b>" . $messages_ini['error']['nntp_fail'] . '</b><br>';

        echo $nntp->get_error_message() . '<br>';
    } else {
        $group_info = $nntp->join_group($_SESSION['newsgroup']);

        if (null === $group_info) {
            echo "<font face=\"$font_family\"><b>" . $messages_ini['error']['group_fail'] . $_SESSION['newsgroup'] . ' </b><br>';

            echo $nntp->get_error_message() . '<br>';
        } else {
            $MIME_Message = $nntp->get_article($reply_id);

            $header = $MIME_Message->get_main_header();

            $subject = htmlescape($header['subject']);

            if (0 != strcasecmp(mb_substr($subject, 0, 3), 'Re:')) {
                $subject = 'Re: ' . $subject;
            }

            $message = '';

            foreach ($MIME_Message->get_all_parts() as $part) {
                if (mb_stristr($part['header']['content-type'], 'text')) {
                    $message .= decode_message_content($part);
                }
            }

            $message = preg_replace("/(.*\r\n)/", '&gt; $1', htmlescape($message));

            $message = $header['from']['name'] . ' ' . $messages_ini['text']['wrote'] . ":\r\n\r\n" . $message;
        }

        $nntp->quit();
    }
}
$name = $xoopsUser->getVar('uname', 'E');
$email = $xoopsUser->getVar('email', 'E');
include 'webnews/compose_template.php';
