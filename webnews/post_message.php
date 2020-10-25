<?php
/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/

?>
<?php
// $nntp = new NNTP($nntp_server, $user, $pass);
$reply_references = '';
if (!$nntp->connect()) {
    echo '<font face="' . $font_family . '"><b>' . $messages_ini['error']['nntp_fail'] . '</b><br>';

    echo $nntp->get_error_message() . '<br>';
} else {
    if (is_requested('reply_id')) {
        $reply_id = get_request('reply_id');

        if (isset($_SESSION['result']) && $_SESSION['result']) {
            $ref_list = $_SESSION['result'][1];

            foreach ($ref_list[$reply_id][1] as $ref) {
                $reply_references .= ' ' . $ref;
            }

            $reply_references .= ' ' . $ref_list[$reply_id][0];
        } else {
            $group_info = $nntp->join_group($_SESSION['newsgroup']);

            if (null === $group_info) {
                $error_messages[] = '<b>' . $messages_ini['error']['group_fail'] . $_SESSION['newsgroup'] . '</b><br>' . $nntp->get_error_message();
            } else {
                $MIME_Message = $nntp->get_header($reply_id);

                $header = $MIME_Message->get_main_header();

                if (null === $header) {
                    $error_messages[] = $messages_ini['error']['header_fail'] . "$reply_id. " . $nntp->get_error_message();
                } else {
                    $reply_references = $header['references'] . ' ' . $header['message-id'];
                }
            }
        }

        $reply_references = trim($reply_references);
    }

    $header = [];

    // Copy the request parameter

    if (is_requested('subject')) {
        $subject = get_request('subject');
    }

    if (is_requested('groups')) {
        $groups = get_request('groups');
    }

    if (is_requested('name')) {
        $name = get_request('name');
    }

    if (is_requested('email')) {
        $email = get_request('email');
    }

    if (is_requested('attachment')) {
        $attachment = get_request('attachment');
    }

    if (is_requested('message')) {
        $message = get_request('message');
    }

    // Done

    if (is_requested('post')) {
        if (!isset($subject) || (0 == mb_strlen($subject))) {
            $subject = '(no subject)';
        }

        if (isset($groups) && (0 != count($groups))) {
            foreach ($groups as $group) {
                if (in_array($group, $newsgroups_list, true)) {
                    $news[] = $group;
                }
            }
        } else {
            $error_messages[] = $messages_ini['error']['no_newsgroup'];
        }

        if (!isset($name) || (0 == mb_strlen($name))) {
            $error_messages[] = $messages_ini['error']['no_name'];
        }

        if (!isset($email) || (0 == mb_strlen($email)) || !validate_email($email)) {
            $error_messages[] = $messages_ini['error']['no_email'];
        } /*else if (!check_email_list($email)) {
$error_messages[] = "Your e-mail address is not in the authorized list. Please contact the administrator.";
}
*/

        $files = [];

        if (isset($attachment)) {
            $file_size = 0;

            foreach ($_FILES as $file) {
                if (is_uploaded_file($file['tmp_name'])) {
                    $files[] = $file;

                    $file_size += filesize($file['tmp_name']);

                    if ($file_size > $upload_file_limit) {
                        $error_messages[] = $messages_ini['error']['exceed_size'] . ($upload_file_limit >> 10) . 'Kb';

                        break;
                    }
                }
            }
        }

        // Strip all the slashes

        if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $subject = stripslashes($subject);

            $name = stripslashes($name);

            $email = stripslashes($email);

            $message = stripslashes($message);
        }

        if (0 == count($error_messages)) {
            ?>
            <form action="index.php">
            <font face="Tahoma, Sans-Serif">
                <table cellspacing="2" cellpadding="2" border="0" width="95%">
                    <tr>
                        <td nowrap="true">
                            <input type="hidden" name="refresh" value="true">
                            <input type="submit" value="<?php echo $messages_ini['control']['return']; ?>" style="<?php echo $form_style_bold; ?>"></td>
                    </tr>
                </table>
            </font>
            <?php
            $_SESSION['name'] = $name;

            $_SESSION['email'] = $email;

            if ($MIME_Message = $nntp->post_article($subject, $name, $email, $news, $reply_references, $message, $files)) {
                echo '<center><font face="' . $font_family . "\" size=\"$font_size\"><b>" . $messages_ini['text']['posted'] . '</b></font></center><br>';

                include 'webnews/article_template.php';
            } else {
                echo '<font face="' . $font_family . '"><b>' . $messages_ini['error']['post_fail'] . '</b><br>';

                echo $nntp->get_error_message() . '<br>';
            }

            unset($_SESSION['attach_count']);
        } ?>
        </form>
        <?php
    }

    if (is_requested('add_file') || (0 != count($error_messages))) {
        $subject = htmlescape($subject);

        $name = htmlescape($name);

        $email = htmlescape($email);

        include 'webnews/compose_template.php';
    }

    $nntp->quit();
}
?>
