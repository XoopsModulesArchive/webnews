<?php
/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/

?>
<font face="<?php echo $font_family; ?>">
    <table cellspacing="2" cellpadding="2" border="0" width="100%">
        <tr>
            <?php
            if (!is_requested('art_group') || (0 == strcmp(get_request('art_group'), $_SESSION['newsgroup']))) {
                ?>
                <td nowrap="true">
                    <form action="index.php">
                        <input type="hidden" name="compose" value="reply">
                        <input type="hidden" name="mid" value="<?php echo $article_id ?>">
                        <input type="submit" value="<?php echo $messages_ini['control']['reply']; ?>" style="<?php echo $form_style_bold; ?>"></form>
                </td>
                <?php
            }
            ?>
            <td nowrap="true" width="100%">
                <form action="index.php">
                    <input type="hidden" name="mid" value="<?php echo $article_id; ?>">
                    <input type="hidden" name="renew" value="false">
                    <input type="submit" value="<?php echo $messages_ini['control']['return']; ?>" style="<?php echo $form_style_bold; ?>"></form>
            </td>
        </tr>
    </table>
    <?php
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
        }

        if (null != $group_info) {
            $MIME_Message = $nntp->get_article($article_id);

            if (null === $MIME_Message) {
                echo '<b>' . $messages_ini['error']['article_fail'] . "$article_id </b><br>";

                echo $nntp->get_error_message() . '<br>';
            } else {
                include 'webnews/article_template.php';
            }
        }

        $nntp->quit();
    }
    ?>
</font>
