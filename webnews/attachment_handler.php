<?php
/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/

// $nntp = new NNTP($nntp_server, $user, $pass);
if (!$nntp->connect()) {
    echo '<b>' . $messages_ini['error']['nntp_fail'] . '</b><br>';

    echo $nntp->get_error_message() . '<br>';
} else {
    if (is_requested('art_group')) {
        $group_info = $nntp->join_group(get_request('art_group'));
    } else {
        $group_info = $nntp->join_group($_SESSION['newsgroup']);
    }

    if (null === $group_info) {
        echo '<b>' . $messages_ini['error']['group_fail'] . $_SESSION['newsgroup'] . ' </b><br>';

        echo $nntp->get_error_message() . '<br>';
    } elseif (isset($attachment_id) && isset($message_id)) {
        $MIME_Message = $nntp->get_article($message_id);

        $nntp->quit();

        if ($MIME_Message->get_total_part() > $attachment_id) {
            $header = $MIME_Message->get_part_header($attachment_id);

            $body = $MIME_Message->get_part_body($attachment_id);

            if (0 == strcmp($header['content-type'], '')) {
                header('Content-Type: text/html');

                echo $messages_ini['error']['request_fail'];
            } else {
                ob_end_clean();

                $pos = mb_strpos($header['content-type'], ';');

                if (false !== $pos) {
                    $header['content-type'] = mb_substr($header['content-type'], 0, $pos);
                }

                header('Content-Type: ' . $header['content-type']);

                header('Content-Disposition: ' . $header['content-disposition']);

                decode_message_content_output($MIME_Message->get_part($attachment_id));

                $nntp->quit();

                exit(0);
            }
        } else {
            header('Content-Type: text/html');

            echo $messages_ini['error']['multipart_fail'];
        }
    } else {
        header('Content-Type: text/html');

        echo $messages_ini['error']['request_fail'];
    }

    $nntp->quit();
}
