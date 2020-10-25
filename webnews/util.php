<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
require 'webnews/uucoder.php';
$MIME_TYPE_MAP = [
    'txt' => 'text/plain',
'html' => 'text/html',
'htm' => 'text/html',
'aif' => 'audio/x-aiff',
'aiff' => 'audio/x-aiff',
'aifc' => 'audio/x-aiff',
'wav' => 'audio/wav',
'gif' => 'image/gif',
'jpg' => 'image/jpeg',
'jpeg' => 'image/jpeg',
'tif' => 'image/tiff',
'tiff' => 'image/tiff',
'png' => 'image/x-png',
'xbm' => 'image/x-xbitmap',
'bmp' => 'image/bmp',
'avi' => 'video/x-msvideo',
'mpg' => 'video/mpeg',
'mpeg' => 'video/mpeg',
'mpe' => 'video/mpeg',
'ai' => 'application/postscript',
'eps' => 'application/postscript',
'ps' => 'application/postscript',
'hqx' => 'application/mac-binhex40',
'pdf' => 'application/pdf',
'zip' => 'application/x-zip-compressed',
'gz' => 'application/x-gzip-compressed',
'doc' => 'application/msword',
'xls' => 'application/vnd.ms-excel',
'ppt' => 'application/vnd.ms-powerpoint',
];
function decode_MIME_header($str)
{
    while (preg_match("/(.*)=\?.*\?q\?(.*)\?=(.*)/i", $str, $matches)) {
        $str = str_replace('_', ' ', $matches[2]);

        $str = $matches[1] . quoted_printable_decode($str) . $matches[3];
    }

    while (preg_match("/=\?.*\?b\?.*\?=/i", $str)) {
        $str = preg_replace("/(.*)=\?.*\?b\?(.*)\?=(.*)/ie", "'$1'.base64_decode('$2').'$3'", $str);
    }

    return $str;
}

function encode_MIME_header($str)
{
    if (is_non_ASCII($str)) {
        $result = '=?ISO-8859-1?Q?';

        for ($i = 0, $iMax = mb_strlen($str); $i < $iMax; $i++) {
            $ascii = ord($str[$i]);

            if (0x20 == $ascii) { // Space
                $result .= '_';
            } elseif ((0x3D == $ascii) || (0x3F == $ascii) || (0x5F == $ascii) || ($ascii > 0x7F)) { // =, ?, _, 8 bit
                $result .= '=' . dechex($ascii);
            } else {
                $result .= $str[$i];
            }
        }

        $result .= '?=';
    } else {
        $result = $str;
    }

    return $result;
}

function is_non_ASCII($str)
{
    for ($i = 0, $iMax = mb_strlen($str); $i < $iMax; $i++) {
        if (ord($str[$i]) > 0x7f) {
            return true;
        }
    }

    return false;
}

function htmlescape($str)
{
    $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5);

    return preg_replace('/&amp;#(x?[0-9A-F]+);/', '&#${1};', $str);
}

function chop_str($str, $len)
{
    if (mb_strlen($str) > $len) {
        $str = mb_substr($str, 0, $len - 3) . '...';
    }

    return $str;
}

function format_date($date)
{
    global $today_color;

    global $week_color;

    $current = time();

    $current_date = getdate($current);

    $today = mktime(0, 0, 0, $current_date['mon'], $current_date['mday'], $current_date['year']);

    // $last_week = $today - ($current_date["wday"])*86400;

    $last_week = $today - 518400;

    if ($date >= $today) {
        // Today

        return '<font color="#' . $today_color . '">' . date("\T\o\d\a\y h:i a", $date) . '</font>';
    } elseif ($date >= $last_week) {
        // Within one week

        return '<font color="#' . $week_color . '">' . date('D, h:i a', $date) . '</font>';
    }

    return date('d-m-Y h:i a', $date);
}

function decode_sender($sender)
{
    if (preg_match("/(['|\"])?(.*)(?(1)['|\"]) <([\w\-=!#$%^*'+\\.={}|?~]+@[\w\-=!#$%^*'+\\.={}|?~]+[\w\-=!#$%^*'+\\={}|?~])>/", $sender, $matches)) {
        // Match address in the form: Name <email@host>

        $result['name'] = $matches[2];

        $result['email'] = $matches[count($matches) - 1];
    } elseif (preg_match("/([\w\-=!#$%^*'+\\.={}|?~]+@[\w\-=!#$%^*'+\\.={}|?~]+[\w\-=!#$%^*'+\\={}|?~]) \((.*)\)/", $sender, $matches)) {
        // Match address in the form: email@host (Name)

        $result['email'] = $matches[1];

        $result['name'] = $matches[2];
    } else {
        // Only the email address present

        $result['name'] = $sender;

        $result['email'] = $sender;
    }

    $result['name'] = str_replace('"', '', $result['name']);

    $result['name'] = str_replace("'", '', $result['name']);

    return $result;
}

function replace_links($matches)
{
    if (!preg_match("/^(?:http|https|ftp|ftps|news):\/\//i", $matches[1])) {
        return "<a href=\"mailto:$matches[2]\">$matches[2]</a>";
    }

    return $matches[1] . $matches[2];
}

function add_html_links($str)
{
    // Add link for e-mail address

    $str = preg_replace_callback("/((?:http|https|ftp|ftps|news):\/\/.*)?([\w\-=!#$%^*'+\\.={}|?~]+@[\w\-=!#$%^*'+\\.={}|?~]+[\w\-=!#$%^*'+\\={}|?~])/i", 'replace_links', $str);

    // Add link for web and newsgroup

    $str = preg_replace("/(http|https|ftp|ftps|news)(:\/\/[\w;\/?:@&=+$,\-\.!~*'()%#&]+)/i", '<a href="$1$2">$1$2</a>', $str);

    return $str;
}

function validate_email($email)
{
    return preg_match("/[\w\-=!#$%^*'+\\.={}|?~]+@[\w\-=!#$%^*'+\\.={}|?~]+[\w\-=!#$%^*'+\\={}|?~]/", $email);
}

function decode_message_content($part)
{
    $encoding = $part['header']['content-transfer-encoding'];

    if (mb_stristr($encoding, 'quoted-printable')) {
        return quoted_printable_decode($part['body']);
    } elseif (mb_stristr($encoding, 'base64')) {
        return base64_decode($part['body'], true);
    } elseif (mb_stristr($encoding, 'uuencode')) {
        return uudecode($part['body']);
    }   // No need to decode

    return $part['body'];
}

function decode_message_content_output($part)
{
    $encoding = $part['header']['content-transfer-encoding'];

    if (mb_stristr($encoding, 'quoted-printable')) {
        echo quoted_printable_decode($part['body']);
    } elseif (mb_stristr($encoding, 'base64')) {
        echo base64_decode($part['body'], true);
    } elseif (mb_stristr($encoding, 'uuencode')) {
        uudecode_output($part['body']);
    } else { // No need to decode
        echo $part['body'];
    }
}

// This function return an appropriately encoded message body.
function create_message_body($message, $files, $boundary = '')
{
    $message_body = '';

    // Need to process the message to change line begin with . to ..

    $message = preg_replace(["/\r\n/", "/^\.(.*)/m", "/\n/"], ["\n", '..$1', "\r\n"], $message);

    if (0 != count($files)) { // Handling uploaded files. Format it as MIME multipart message
        // Read the content of each file

        $counter = 0;

        $message_body .= "This is a multi-part message in MIME format\r\n";

        $message_body .= $boundary . "\r\n";

        $message_body .= "Content-Type: text/plain\r\n";

        $message_body .= "\r\n";

        $message_body .= $message;

        $message_body .= "\r\n\r\n";

        foreach ($files as $file) {
            $message_body .= $boundary . "\r\n";

            $message_body .= 'Content-Type: ' . $file['type'] . "\r\n";

            $message_body .= "Content-Transfer-Encoding: base64\r\n";

            $message_body .= 'Content-Disposition: inline; filename="' . $file['name'] . "\"\r\n";

            $message_body .= "\r\n";

            $fd = fopen($file['tmp_name'], 'rb');

            $tmp_buf = '';

            while ($buf = fread($fd, 1024)) {
                $tmp_buf .= $buf;
            }

            fclose($fd);

            $tmp_buf = base64_encode($tmp_buf);

            $offset = 0;

            while ($offset < mb_strlen($tmp_buf)) {
                $message_body .= mb_substr($tmp_buf, $offset, 72) . "\r\n";

                $offset += 72;
            }
        }

        $message_body .= $boundary . "--\r\n";
    } else { // Write the plain text only
        $message_body .= $message;
    }

    return $message_body;
}

function filter_html($body)
{
    global $filter_script;

    // rename the body tag

    $body = preg_replace("/<(\s*)(\/?)(\s*)(body)(.*?)>/is", '<${2}x${4}${5}>', $body);

    // Filter the unwanted tag block

    $filter_list = '(style';

    if ($filter_script) {
        $filter_list .= '|script';
    }

    $filter_list .= ')';

    return preg_replace("/<(\s*)" . $filter_list . "(.*?)>(.*?)<(\s*)\/(\s*)" . $filter_list . "(\s*)>/si", '', $body);
}

/*
function check_email_list($email) {
global $namelist;
clearstatcache();
if (isset($namelist) && file_exists($namelist)) {
$db = dba_open($namelist, "r", "gdbm");
return dba_exists($email, $db);
} else {
return TRUE;
}
}
*/
function get_content_type($file)
{
    global $MIME_TYPE_MAP;

    $extension = mb_strtolower(mb_substr(mb_strrchr($file, '.'), 1));

    if (array_key_exists($extension, $MIME_TYPE_MAP)) {
        return $MIME_TYPE_MAP[$extension];
    }

    return 'application/octet-stream';
}

function is_requested($name)
{
    return (isset($_GET[$name]) || isset($_POST[$name]));
}

function get_request($name)
{
    if (isset($_GET[$name])) {
        return $_GET[$name];
    } elseif (isset($_POST[$name])) {
        return $_POST[$name];
    }

    return '';
}

function verify_login($username, $password)
{
    global $nntp_server;

    global $proxy_server;

    global $proxy_port;

    global $proxy_user;

    global $proxy_pass;

    if (mb_strlen($username) > 0) { // Won't allow empty user name
        // Create a dummy connection for authentication

        $nntp = new NNTP($nntp_server, $username, $password, $proxy_server, $proxy_port, $proxy_user, $proxy_pass);

        $result = $nntp->connect();

        $nntp->quit();

        return $result;
    }

    return false;
}

function construct_url($name)
{
    $result = parse_url($name);

    $url = '';

    $mark = false;

    if (!$result['scheme']) {
        if ('on' != $_SERVER['HTTPS']) {
            $url = 'http';
        } else {
            $url = 'https';
        }
    } else {
        $url = $result['scheme'];
    }

    $url .= '://';

    if ($result['user']) {
        $url .= $result['user'];

        $mark = true;
    }

    if ($result['pass']) {
        $url .= ':' . $result['pass'];

        $mark = true;
    }

    if ($mark) {
        $url .= '@';
    }

    if ($result['host']) {
        $url .= $result['host'];
    } else {
        $url .= $_SERVER['HTTP_HOST'];
    }

    if ('/' != $result['path'][0]) {
        $url .= dirname($_SERVER['REQUEST_URI']) . '/';
    }

    $url .= $result['path'];

    if ($result['query']) {
        $url .= '?' . $result['query'];
    }

    if ($result['fragment']) {
        $url .= '#' . $result['fragment'];
    }

    return $url;
}

function read_ini_file($file, $section = false)
{
    $fp = fopen($file, 'rb');

    if (!$fp) {
        return false;
    }

    $ini = [];

    while (($buf = fgets($fp, 1024))) {
        $buf = trim($buf);

        if (0 == mb_strlen($buf)) {
            continue;
        }

        if (';' != $buf[0]) { // Skip the comment
            if ('[' == $buf[0]) {
                if ($section) {
                    $pos = mb_strpos($buf, ']');

                    if (!$pos) {
                        return false;
                    }

                    $section_name = mb_substr($buf, 1, $pos - 1);

                    $ini[$section_name] = [];
                }
            } elseif (false !== mb_strpos($buf, '=')) {
                [$key, $value] = preg_split('=', $buf, 2);

                $value = preg_replace("/^(['|\"])?(.*?)(?(1)['|\"])/", '${2}', trim($value));

                if ((0 != mb_strlen($key)) && (0 != mb_strlen($value))) {
                    if (isset($section_name)) {
                        $ini[$section_name][$key] = $value;
                    } else {
                        $ini[$key] = $value;
                    }
                }
            }
        }
    }

    fclose($fp);

    return $ini;
}
