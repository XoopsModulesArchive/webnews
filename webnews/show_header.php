<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
$sort_by_list = ['subject', 'from', 'date'];
if (is_requested('sign')) {
    $sign = get_request('sign');
}
if (is_requested('sort')) {
    $sort = get_request('sort');
}
if (isset($refresh)) {
    $page = 1;
} elseif (is_requested('page')) {
    $page = (int)get_request('page');
} elseif (isset($_SESSION['last_page'])) {
    $page = $_SESSION['last_page'];
} else {
    $page = 1;
}
if (is_requested('set_page') && is_requested('msg_per_page')) {
    $message_per_page = get_request('msg_per_page');

    $page = 1;
} elseif (isset($_SESSION['msg_per_page'])) {
    $message_per_page = $_SESSION['msg_per_page'];
}
$_SESSION['last_page'] = $page;
$_SESSION['msg_per_page'] = $message_per_page;
if (!$nntp->connect()) {
    $_SESSION['result'] = null;

    echo '<b>' . $messages_ini['error']['nntp_fail'] . '</b><br>';

    echo $nntp->get_error_message() . '<br>';

    $nntp->quit();

    exit;
}  
    $group_info = $nntp->join_group($_SESSION['newsgroup']);
    if (null === $group_info) {
        $_SESSION['result'] = null;

        echo '<b>' . $messages_ini['error']['group_fail'] . $_SESSION['newsgroup'] . ' </b><br>';

        echo $nntp->get_error_message() . '<br>';

        $_SESSION['newsgroup'] = $default_group;

        $group_info = $nntp->join_group($_SESSION['newsgroup']);

        // $nntp->quit();
        // exit;
    }
    if (null != $group_info && ($renew || (null === $_SESSION['result']))) {
        $_SESSION['result'] = null;

        $_SESSION['article_list'] = $nntp->get_article_list($_SESSION['newsgroup']);

        if (false === $_SESSION['article_list']) {
            unset($_SESSION['article_list']);

            echo '<b>' . $messages_ini['error']['group_fail'] . $_SESSION['newsgroup'] . ' </b><br>';

            echo $nntp->get_error_message() . '<br>';

            $_SESSION['newsgroup'] = $default_group;

            $group_info = $nntp->join_group($_SESSION['newsgroup']);

            // $nntp->quit();
            // exit;
        }

        if (0 == strcmp($message_per_page, 'all')) {
            $start_id = 0;

            $end_id = count($_SESSION['article_list']) - 1;
        } else {
            $end_id = count($_SESSION['article_list']) - $message_per_page * ($page - 1) - 1;

            $start_id = $end_id - $message_per_page + 1;
        }

        if ($start_id < 0) {
            $start_id = 0;
        }

        $result = $nntp->get_message_summary($_SESSION['article_list'][$start_id], $_SESSION['article_list'][$end_id]);

        if ($result) {
            $result[0]->compact_tree();

            $need_sort = true;
        }

        if ($result) {
            krsort($result[1], SORT_NUMERIC);

            reset($result[1]);
        }

        // Set the sorting setting as previous group and force sorting

        if (!isset($sort) && isset($_SESSION['sort_by']) && $need_sort) {
            $sort = $_SESSION['sort_by'];

            $_SESSION['sort_by'] = -1;
        }

        $_SESSION['result'] = $result;
    }
    $nntp->quit();

if ($_SESSION['result']) {
    $root_node = &$_SESSION['result'][0];

    $ref_list = &$_SESSION['result'][1];

    if (!isset($_SESSION['sort_by'])) {
        $_SESSION['sort_by'] = 2;

        $last_sort = -1;

        $_SESSION['sort_asc'] = 0;

        $last_sort_dir = 0;
    } else {
        $last_sort = $_SESSION['sort_by'];

        $last_sort_dir = $_SESSION['sort_asc'];

        if (isset($sort)) {
            $_SESSION['sort_by'] = (int)$sort;

            if ($_SESSION['sort_by'] == $last_sort) {
                $_SESSION['sort_asc'] = (1 == $_SESSION['sort_asc']) ? 0 : 1;
            }
        } else {
            $_SESSION['sort_by'] = $last_sort;
        }
    }

    if (($_SESSION['sort_by'] != $last_sort) || ($_SESSION['sort_asc'] != $last_sort_dir)) {
        $root_node->deep_sort_message($sort_by_list[$_SESSION['sort_by']], $_SESSION['sort_asc']);
    }

    if (isset($sign) && isset($mid)) {
        $message_id = $ref_list[$mid][0];

        $references = $ref_list[$mid][1];

        $node = &$root_node;

        // Search the reference list only when the expand node is not a child of the root

        if (!$node->get_child($message_id)) {
            if (0 != count($references)) {
                foreach ($references as $ref) {
                    $child = &$node->get_child($ref);

                    if (null != $child) {
                        $node = &$child;
                    }
                }
            }
        }

        $node = &$node->get_child($message_id);

        if ($node) {
            if (0 == strcasecmp($sign, 'minus')) {
                $node->set_show_children(false);
            } elseif (0 == strcasecmp($sign, 'plus')) {
                $node->set_show_all_children(true);
            }
        }
    } ?>
    <form action="index.php">
    <font face="<?php echo $font_family; ?>">
    <table cellspacing="2" cellpadding="0" border="0" width="100%">
        <tr>
            <td nowrap="true">
                <input type="submit" name="compose" value="<?php echo $messages_ini['control']['compose']; ?>" style="<?php echo $form_style_bold; ?>"></td>
            <td nowrap="true">
                <input type="submit" name="refresh" value="<?php echo $messages_ini['control']['new_news']; ?>" style="<?php echo $form_style_bold; ?>"></td>
            <td nowrap="true" width="99%">
                <font size="<?php echo $font_size; ?>"><b><?php echo $messages_ini['text']['newsgroup']; ?>:</b></font>
                <select name="group" style="<?php echo $form_style_bold; ?>">
                    <?php
                    while (list($key, $value) = each($newsgroups_list)) {
                        echo "<option value=\"$value\"";

                        if (0 == strcmp($value, $_SESSION['newsgroup'])) {
                            echo ' selected';
                        }

                        echo ">$value\r\n";
                    }

    reset($newsgroups_list); ?>
                </select>
                <input type="submit" value="<?php echo $messages_ini['control']['go']; ?>" style="<?php echo $form_style_bold; ?>">
            </td>
            <td align="right" valign="top" rowspan="2">
                <img src="<?php echo $image_base . 'webnews.gif'; ?>" border="0" width="40" height="40">
            </td>
            <td align="right" valign="top" nowrap="true" rowspan="2"><font size="-2">
                    Web-News v.1.5.7<br>by <a href="http://web-news.sourceforge.net/webnews.html" target="new">Terence Yim</a><br>
                    Xoops Mod by <a href="http://www.aviary.info">ChadK</a></font>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <input type="submit" name="expand" value="<?php echo $messages_ini['control']['expand']; ?>" style="<?php echo $form_style_bold; ?>"><input type="submit" name="collapse" value="<?php echo $messages_ini['control']['collapse']; ?>" style="<?php echo $form_style_bold; ?>">
                <b><font size="<?php echo $font_size; ?>"><?php echo $messages_ini['text']['messages_per_page']; ?>:</font>
                    <select name="msg_per_page" style="<?php echo $form_style_bold; ?>">
                        <?php
                        foreach ($message_per_page_choice as $i) {
                            echo "<option value=\"$i\"";

                            if (0 == strcmp($message_per_page, $i)) {
                                echo ' selected';
                            }

                            if (0 == strcmp($i, 'all')) {
                                echo '>' . $messages_ini['text']['all'];
                            } else {
                                echo ">$i";
                            }
                        } ?>
                    </select>
                    <input type="submit" name="set_page" value="<?php echo $messages_ini['control']['set']; ?>" style="<?php echo $form_style_bold; ?>"
                </b>
                <a href="index.php?rss_feed=<?php echo $message_per_page; ?>&group=<?php echo $_SESSION['newsgroup']; ?>" target="_blank">
                    <img src="<?php echo $image_base . 'rss.gif'; ?>" border="0" width="53" height="18" align="top">
                </a>
            </td>
        </tr>
    </table>
    <?php
    // Display section
    if ($_SESSION['sort_asc']) {
        $arrow_img = $image_base . 'sort_arrow_up.gif';
    } else {
        $arrow_img = $image_base . 'sort_arrow_down.gif';
    } ?>
    <table cellpadding="0" cellspacing="1" border="0" width="100%">
    <tr bgcolor="<?php echo $primary_color; ?>">
        <?php
        echo "<td width=\"65%\"><font size=\"$font_size\" nowrap=\"true\"><b>";

    echo '<a href="index.php?renew=false&sort=0">' . $messages_ini['text']['subject'] . '</a>';

    if (0 == $_SESSION['sort_by']) {
        echo "&nbsp;<img src=\"$arrow_img\" border=\"0\" align=\"absbottom\">";
    }

    echo '</b></font></td>';

    echo "<td width=\"23%\"><font size=\"$font_size\" nowrap=\"true\"><b>";

    echo '<a href="index.php?renew=false&sort=1">' . $messages_ini['text']['sender'] . '</a>';

    if (1 == $_SESSION['sort_by']) {
        echo "&nbsp;<img src=\"$arrow_img\" border=\"0\" align=\"absbottom\">";
    }

    echo '</b></font></td>';

    echo "<td width=\"12%\"><font size=\"$font_size\" nowrap=\"true\"><b>";

    echo '<a href="index.php?renew=false&sort=2">' . $messages_ini['text']['date'] . '</a>';

    if (2 == $_SESSION['sort_by']) {
        echo "&nbsp;<img src=\"$arrow_img\" border=\"0\" align=\"absbottom\">";
    }

    echo '</b></font></td>'; ?>
    </tr>
    <tr>
        <td colspan="3"><font size="<?php echo($font_size - 1); ?>">&nbsp;</font></td>
    </tr>
    <?php
    if ($need_expand) {
        $root_node->set_show_all_children($_SESSION['expand_all']);

        $root_node->set_show_children(true);
    }

    $display_counter = 0;

    display_tree($root_node->get_children(), 0);
}
if (0 != strcasecmp($message_per_page, 'all')) {
    $page_count = ceil((float)count($_SESSION['article_list']) / (float)$message_per_page);

    $start_page = (ceil($page / $pages_per_page) - 1) * $pages_per_page + 1;

    $end_page = $start_page + $pages_per_page - 1;

    if ($end_page > $page_count) {
        $end_page = $page_count;
    }
} else { // Show All
    $page_count = 0;
}
if ((0 != $page_count) && ((1 != $start_page) || ($start_page != $end_page))) {
    ?>
    <tr bgcolor="#<?php echo $tertiary_color; ?>">
        <td colspan="3">&nbsp;</td>
    </tr>
    <tr bgcolor="#<?php echo $tertiary_color; ?>">
        <td colspan="4" align="center">
            <font size="<?php echo $font_size; ?>">
                <b><?php echo $messages_ini['text']['page']; ?>: </b>
                <?php
                if (1 != $page) {
                    echo '<a href="index.php?page=' . ($page - 1) . '"><img src="' . $image_base . 'previous_arrow.gif" align="absmiddle" border="0"></a>';
                }

    echo '&nbsp;';

    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($page == $i) {
            echo $i;
        } else {
            echo "<a href=\"index.php?page=$i\">$i</a>";
        }

        echo '&nbsp;';
    }

    if ($page != $page_count) {
        echo '<a href="index.php?page=' . ($page + 1) . '"><img src="' . $image_base . 'next_arrow.gif" align="absmiddle" border="0"></a>';
    }

    echo '&nbsp;&nbsp;&nbsp;&nbsp;';

    if (1 != $start_page) {
        echo '<b><a href="index.php?page=' . ($start_page - 1) . '">' . $messages_ini['text']['previous'] . (string)$pages_per_page . $messages_ini['text']['page_quality'] . '</a></b>&nbsp;&nbsp;';
    }

    if ($end_page != $page_count) {
        echo '<b><a href="index.php?page=' . ($end_page + 1) . '">' . $messages_ini['text']['next'] . (string)$pages_per_page . $messages_ini['text']['page_quality'] . "</a></b>\r\n";
    } ?>
            </font></td>
    </tr>
    <?php
}
?>
    </table>
    </form>
    </font>
<?php
function display_tree($nodes, $level, $indent = '')
{
    global $image_base;

    global $font_size;

    global $primary_color;

    global $secondary_color;

    global $tertiary_color;

    global $display_counter;

    global $subject_length_limit;

    global $sender_length_limit;

    $count = 0;

    $last_index = count($nodes) - 1;

    $old_indent = $indent;

    foreach ($nodes as $node) {
        $message_info = $node->get_message_info();

        $is_first = (0 == $count) ? 1 : 0;

        $is_last = ($count == $last_index) ? 1 : 0;

        if (0 == $node->count_children()) {
            if ($is_first && $is_last) {
                if (0 == $level) {
                    $sign = '<img src="' . $image_base . 'white.gif" width="15" height="19" align="absbottom" alt=".">';
                } else {
                    $sign = '<img src="' . $image_base . 'bar_L.gif" width="15" height="19" align="absbottom" alt="\\">';
                }
            } elseif ($is_first) {
                if (0 == $level) {
                    $sign = '<img src="' . $image_base . 'bar_7.gif" width="15" height="19" align="absbottom" alt="*">';
                } else {
                    $sign = '<img src="' . $image_base . 'bar_F.gif" width="15" height="19" align="absbottom" alt="|">';
                }
            } elseif ($is_last) {
                $sign = '<img src="' . $image_base . 'bar_L.gif" width="15" height="19" align="absbottom" alt="\\">';
            } else {
                $sign = '<img src="' . $image_base . 'bar_F.gif" width="15" height="19" align="absbottom" alt="|">';
            }
        } else {
            if ($node->is_show_children()) {
                $sign = 'minus';

                $alt = '-';
            } else {
                $sign = 'plus';

                $alt = '+';
            }

            $link = '<a href="index.php?renew=false&mid=' . $message_info->nntp_message_id . '&sign=' . $sign . '">';

            if ($is_first && $is_last && (0 == $level)) {
                $sign = $link . '<img src="' . $image_base . 'sign_' . $sign . '_single.gif" width="15" height="19" align="absbottom" border="0" alt="' . $alt . '"></a>';
            } elseif (($is_first) && (0 == $level)) {
                $sign = $link . '<img src="' . $image_base . 'sign_' . $sign . '_first.gif" width="15" height="19" align="absbottom" border="0" alt="' . $alt . '"></a>';
            } elseif ($is_last) {
                $sign = $link . '<img src="' . $image_base . 'sign_' . $sign . '_last.gif" width="15" height="19" align="absbottom" border="0" alt="' . $alt . '"></a>';
            } else {
                $sign = $link . '<img src="' . $image_base . 'sign_' . $sign . '.gif" width="15" height="19" align="absbottom" border="0" alt="' . $alt . '"></a>';
            }
        }

        if (0 == ($display_counter % 2)) {
            echo '<tr bgcolor="#' . $secondary_color . "\">\r\n";
        } else {
            echo '<tr bgcolor="#' . $tertiary_color . "\">\r\n";
        }

        $display_counter++;

        // echo "<tr>\r\n";

        echo "<td nowrap=\"true\"><font size=\"$font_size\">\r\n";

        echo '<a name="' . $message_info->nntp_message_id . '">';

        echo $old_indent;

        echo $sign . '<img src="' . $image_base . 'message.gif" width="13" height="13" align="absmiddle" alt="#">&nbsp;';

        echo '<a href="index.php?art_group=' . $_SESSION['newsgroup'] . '&article_id=' . $message_info->nntp_message_id . '">';

        echo htmlescape(chop_str($message_info->subject, $subject_length_limit - $level * 3)) . "</a></font></td>\r\n";

        echo "<td nowrap=\"true\"><font size=\"$font_size\">\r\n";

        if ($_SESSION['auth']) {
            echo '<a href="mailto:' . htmlescape($message_info->from['email']) . '">';
        }

        echo htmlescape(chop_str($message_info->from['name'], $sender_length_limit));

        if ($_SESSION['auth']) {
            echo '</a>';
        }

        echo "</font></td>\r\n";

        echo "<td nowrap=\"true\"><font size=\"$font_size\">" . format_date($message_info->date) . "</font></td>\r\n";

        echo "</tr>\r\n";

        if ($is_last) {
            $indent = $old_indent . '<img src="' . $image_base . 'white.gif" width="15" height="19" align="absbottom" alt=".">';
        } else {
            $indent = $old_indent . '<img src="' . $image_base . 'bar_1.gif" width="15" height="19" align="absbottom" alt="|">';
        }

        if ($node->is_show_children() && (0 != $node->count_children())) {
            display_tree($node->get_children(), $level + 1, $indent);
        }

        $count++;
    }
}

?>
