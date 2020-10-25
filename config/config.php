<?php
/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/

/******************************************************************/
/* SERVER SETTINGS  */
/* This part configurate the server settings */
/******************************************************************/
// NNTP Server setting
$nntp_server = 'news.athenanews.com';
$user = 'newslogin';
$pass = 'newspassword';
// Proxy Server settings. Set it to empty string for not using it
$proxy_server = '';
$proxy_port = '';
$proxy_user = '';
$proxy_pass = '';
// Session name. Set it to a unique string that can represent your site.
$session_name = 'webnews';
// List of subscribed newsgroups
$newsgroups_list = [
    'alt.pets.parrots.marketplace',
    'alt.pets.parrots.misc',
    'alt.pets.parrots.african-grey',
    'alt.pets.parrots.amazons',
    'alt.pets.parrots.budgerigars',
    'alt.pets.parrots.cockatiels',
    'alt.pets.parrots.cockatoo',
    'alt.pets.parrots.jardines',
    'alt.pets.parrots.macaws',
    'alt.pets.parrots.senegal-parrots',
    'rec.pets.birds',
];
$default_group = 'alt.pets.parrots.marketplace';
/******************************************************************/
/* SECURITY SETTINGS  */
/* This part configurate the security settings */
/******************************************************************/
// auth_level = 1 ------ No need to perform authentication
// auth_level = 2 ------ Perform authentication only when posting message
// auth_level = 3 ------ Perform authentication in any operation
$auth_level = 2;
// The URL of the page shown after user logout
// It can be a relative or absolute address
// If protocol other than HTTP or HTTPS is used, please use absolute path
// You can also use the variable "$_SERVER['HTTP_HOST']" to extract the current host name
// e.g. $logout_url = "ftp://".$_SERVER['HTTP_HOST']."/mypath";
$logout_url = 'http://www.aviary.info/logout.php';
// Realm to be used in the user authetication
$realm = 'Aviary.info Web-News';
/******************************************************************/
/* PAGE DISPLAY SETTINGS  */
/* This part set the limit constants */
/******************************************************************/
// Page splitting settings
$message_per_page = 25;
$message_per_page_choice = [25, 50, 75, 100, 'all'];
$pages_per_page = 10;
$text_ini = 'config/messages.ini';
// $text_ini = "config/messages_zh_tw.ini";
// $text_ini = "config/messages_zh_tw_utf8.ini";
// $text_ini = "config/messages_zh_cn.ini";
// $text_ini = "config/messages_zh_cn_utf8.ini";
// Filter the javascript or jscript
$filter_script = true;
/******************************************************************/
/* DEFAULT/LIMIT VALUES SETTINGS  */
/* This part set the the default values or limits */
/******************************************************************/
// TRUE if the message tree is all expanded when first loaded, FALSE otherwise
$default_expanded = false;
// TRUE if posting across several subscribed newsgroups is allowed
$allow_cross_post = false;
// Upload file size limit
$upload_file_limit = 1048576; //1M
// The length limit for the subject and sender
$subject_length_limit = 100;
$sender_length_limit = 20;
// Path to the images
$image_base = 'images/webnews/';
/******************************************************************/
/* COLOUR AND FONT SETTINGS  */
/* This part set the colour scheme and the font style */
/******************************************************************/
// Notice that the background color, text, link, active link and visited link color are controlled
// in the <BODY> tag of template.php. They are not set in here
$today_color = 'ff0000'; // Colour of the date display if the date is today
$week_color = '00aa00'; // Colour of the date display if the date is within a week
$error_color = 'ff0000'; // Colour of the error messages
// Primary colour is the deepest colour and tertiary colour is the lightest colour
$primary_color = 'C1DFFA';
$secondary_color = 'EAF6FF';
$tertiary_color = 'FFFFFF';
$font_family = 'Tahoma, Sans-Serif';
$font_size = '-1';
$form_style = 'font-family: ' . $font_family . '; font-size: 75%';
$form_style_bold = $form_style . '; font-weight: bold';
/******************************************************************/
/* TEMPLATE SETTINGS  */
/******************************************************************/
// The template script should contain at least 3 statement as:
//
// ob_start();
// include $content_page;
// ob_end_flush();
//
// If you want to support autoscroll, please also include the following in the BODY tag
//
// if (isset($on_load_script)) {
// echo "onLoad=\"$on_load_script\"";
// }
$template = 'template.php';
// template2.php includes a fancy welcome header
// $template = "template2.php";
