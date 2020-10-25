<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
$header = $MIME_Message->get_main_header();
$parts = $MIME_Message->get_all_parts();
if (is_requested('art_group')) {
    $group = get_request('art_group');
} else {
    $group = $_SESSION['newsgroup'];
}
?>
<font face="<?php echo $font_family; ?>">
    <table cellpadding="5" cellspacing="0" border="0" align="left" width="100%">
        <tr>
            <td bgcolor="<?php echo $primary_color; ?>" width="15%" valign="top"><font size="<?php echo $font_size; ?>"><b><?php echo $messages_ini['text']['subject']; ?></b></font></td>
            <td bgcolor="<?php echo $secondary_color; ?>"><font size="<?php echo $font_size; ?>"><?php echo htmlescape($header['subject']); ?></td>
        </tr>
        <tr>
            <td bgcolor="<?php echo $primary_color; ?>" width="15%" valign="top"><font size="<?php echo $font_size; ?>"><b><?php echo $messages_ini['text']['from']; ?></b></font></td>
            <td bgcolor="<?php echo $secondary_color; ?>"><font size="<?php echo $font_size; ?>">
                    <?php
                    if (is_requested('post') || $_SESSION['auth']) {
                        echo '<a href="mailto:' . htmlescape($header['from']['email']) . '">';
                    }
                    echo htmlescape($header['from']['name']);
                    if (is_requested('post') || $_SESSION['auth']) {
                        echo htmlescape(' <' . $header['from']['email'] . '>') . '</a>';
                    }
                    ?>
            </td>
        </tr>
        <tr>
            <td bgcolor="<?php echo $primary_color; ?>" width="15%" valign="top"><font size="<?php echo $font_size; ?>"><b><?php echo $messages_ini['text']['date']; ?></b></font></td>
            <td bgcolor="<?php echo $secondary_color; ?>"><font size="<?php echo $font_size; ?>"><?php echo $header['date']; ?></td>
        </tr>
        <tr>
            <td bgcolor="<?php echo $primary_color; ?>" width="15%" valign="top"><font size="<?php echo $font_size; ?>"><b><?php echo $messages_ini['text']['newsgroups']; ?></b></font></td>
            <td bgcolor="<?php echo $secondary_color; ?>"><font size="<?php echo $font_size; ?>"><?php echo $header['newsgroups']; ?></td>
        </tr>
        <!--
<tr>
<td bgcolor="<?php echo $primary_color; ?>" width="15%" valign="top"><font size="<?php echo $font_size; ?>"><b>Content-Type</b></font></td>
<td bgcolor="<?php echo $secondary_color; ?>"><font size="<?php echo $font_size; ?>"><?php echo $header['content-type']; ?></td>
</tr>
-->
        <?php
        if (count($parts) > 1) { // We've got attachment
            echo "<tr>\r\n";

            echo "<td bgcolor=\"$primary_color\" width=\"15%\" valign=\"top\"><font size=\"$font_size\"><b>" . $messages_ini['text']['attachments'] . "</b></font></td>\r\n";

            echo "<td bgcolor=\"$secondary_color\"><font size=\"$font_size\">\r\n";

            $attach_file = '';

            for ($i = 1, $iMax = count($parts); $i < $iMax; $i++) {
                if ((1 != $i) && (0 == ($i - 1) % 5)) {
                    $attach_file .= "<br>\r\n";
                }

                if (0 != strcmp($parts[$i]['filename'], '')) {
                    $attach_file .= '<a href="index.php?art_group=' . $group . '&message_id=' . $article_id . '&attachment_id=' . $i . '" target="_blank">' . $parts[$i]['filename'] . '</a>,&nbsp;';
                } else {
                    $attach_file .= '<a href="index.php?art_group=' . $group . '&message_id=' . $article_id . '&attachment_id=' . $i . '" target="_blank">' . $messages_ini['text']['no_name'] . " $i</a>,&nbsp;";
                }
            }

            if (mb_strlen($attach_file) > 0) {
                $attach_file = mb_substr($attach_file, 0, -7);
            }

            echo $attach_file;

            echo "</td>\r\n";

            echo "</tr>\r\n";
        }
        $count = 0;
        foreach ($parts as $part) {
            if (mb_stristr($part['header']['content-type'], 'text/html')) { // HTML
                $body = filter_html(decode_message_content($part));

                // Replace the image link for internal resources

                $content_map = $MIME_Message->get_content_map();

                $search_array = [];

                $replace_array = [];

                foreach ($content_map as $cid => $aid) {
                    $cid = mb_substr($cid, 1, -1);

                    $search_array[] = 'cid:' . $cid;

                    $replace_array[] = 'index.php?art_group=' . $group . '&message_id=' . $article_id . '&attachment_id=' . $aid;
                }

                $body = str_replace($search_array, $replace_array, $body);

                echo '<tr><td colspan="2"><div>' . $body . '</div><br></td></tr>';
            } elseif (mb_stristr($part['header']['content-type'], 'text')) { // Treat all other form of text as plain text
                echo "<tr><td colspan=\"2\"><font size=\"$font_size\"><br>";

                $body = decode_message_content($part);

                $body = htmlescape($body);

                $body = preg_replace(
                    ["/\r\n/", '/(^&gt;.*)/m', "/\t/", '/ /'],
                    ["<br>\r\n", '<i>$1</i>', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', '&nbsp;&nbsp;'],
                    add_html_links($body)
                );

                echo $body . '<br></td></tr>';
            } elseif (preg_match("/^image\/(gif|jpeg|pjpeg)/i", $part['header']['content-type'])) {
                echo '<tr><td colspan="2" align="center">';

                echo '<hr width="100%"><br>';

                echo "<img src=\"index.php?art_group=$group&message_id=$article_id&attachment_id=$count\" border=\"0\">";

                echo "<br></td></tr>\r\n";
            }

            $count++;
        }
        ?>
    </table>
</font>
