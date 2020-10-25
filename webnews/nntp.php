<?php

/*
Web-News v.1.5.7 NNTP<->WWW gateway
This PHP script is licensed under the GPL
Author: Terence Yim
E-mail: chtyim@gmail.com
Homepage: http://web-news.sourceforge.net
*/
require 'webnews/util.php';
require 'webnews/MIME_Message.php';
define('NNTP_PORT', 119);
// Define the return status code
define('SERVER_READY', 200);
define('SERVER_READY_NO_POST', 201);
define('GROUP_SELECTED', 211);
define('INFORMATION_FOLLOWS', 215);
define('ARTICLE_HEAD_BODY', 220);
define('ARTICLE_HEAD', 221);
define('ARTICLE_BODY', 222);
define('ARTICLE_OVERVIEW', 224);
define('ARTICLE_POST_OK', 240);
define('ARTICLE_POST_READY', 340);
define('AUTH_ACCEPT', 281);
define('MORE_AUTH_INFO', 381);
define('AUTH_REQUIRED', 480);
define('AUTH_REJECTED', 482);
define('NO_PERMISSION', 502);

class NNTP
{
    public $nntp;

    public $server;

    public $user;

    public $pass;

    public $proxy_server;

    public $proxy_port;

    public $proxy_user;

    public $proxy_pass;

    public $use_proxy;

    public $error_number;

    public $error_message;

    public function __construct($server, $user = '', $pass = '', $proxy_server = '', $proxy_port = '', $proxy_user = '', $proxy_pass = '')
    {
        $this->server = $server;

        $this->user = $user;

        $this->pass = $pass;

        $this->proxy_server = $proxy_server;

        $this->proxy_port = $proxy_port;

        $this->proxy_user = $proxy_user;

        $this->proxy_pass = $proxy_pass;

        if ((0 != strcmp($this->proxy_server, '')) && (0 != strcmp($this->proxy_port, ''))) {
            $this->use_proxy = true;
        } else {
            $this->use_proxy = false;
        }
    }

    /* Open a TCP connection to the specific server
    Return: TRUE - open succeeded
    FALSE - open failed
    */

    public function connect()
    {
        if ($this->nntp) { // We won't try to re-connect an already opened connection
            return true;
        }

        if ($this->use_proxy) {
            $this->nntp = fsockopen($this->proxy_server, $this->proxy_port, $this->error_number, $this->error_message);
        } else {
            $this->nntp = fsockopen($this->server, NNTP_PORT, $this->error_number, $this->error_message);
        }

        if ($this->nntp) {
            if ($this->use_proxy) {
                $response = 'CONNECT ' . $this->server . ':' . NNTP_PORT . " HTTP/1.0\r\n";

                if ((0 != strcmp($this->proxy_user, '')) && (0 != strcmp($this->proxy_pass, ''))) {
                    $response .= 'Proxy-Authorization: Basic '; // Only support Basic authentication type

                    $response .= base64_encode($this->proxy_user . ':' . $this->proxy_pass);

                    $response .= "\r\n";
                }

                $response = $this->send_request($response);

                if (mb_strstr($response, '200 Connection established')) {
                    fgets($this->nntp, 4096); // Skip an empty line

                    $response = $this->parse_response(fgets($this->nntp, 4096));
                } else {
                    $response['status'] = NO_PERMISSION; // Assign it to something dummy

                    $response['message'] = 'No permission';
                }
            } else {
                $response = $this->parse_response(fgets($this->nntp, 4096));
            }

            if ((SERVER_READY == $response['status']) || (SERVER_READY_NO_POST == $response['status'])) {
                $this->send_request('mode reader');

                if (0 != strcmp($this->user, '')) {
                    $response = $this->parse_response($this->send_request('authinfo user ' . $this->user));

                    if (MORE_AUTH_INFO == $response['status']) {
                        $response = $this->parse_response($this->send_request('authinfo pass ' . $this->pass));

                        if (AUTH_ACCEPT == $response['status']) {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }

            $this->error_number = $response['status'];

            $this->error_message = $response['message'];
        }

        return false;
    }

    /* Close the TCP Connection
    */

    public function quit()
    {
        if ($this->nntp) {
            $this->send_request('quit');

            fclose($this->nntp);

            $this->nntp = null;
        }
    }

    public function parse_response($response)
    {
        $status = mb_substr($response, 0, 3);

        $message = str_replace("\r\n", '', mb_substr($response, 4));

        return [
            'status' => (int)$status,
            'message' => $message,
        ];
    }

    public function send_request($request)
    {
        if ($this->nntp) {
            fwrite($this->nntp, $request . "\r\n");

            fflush($this->nntp);

            return fgets($this->nntp, 4096);
        }
    }

    public function read_response_body()
    {
        if ($this->nntp) {
            $result = '';

            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                $result .= $buf;

                $buf = fgets($this->nntp, 4096);
            }

            return $result;
        }
    }

    public function join_group($group)
    {
        if ($this->nntp) {
            $buf = $this->send_request('group ' . $group);

            $response = $this->parse_response($buf);

            if (GROUP_SELECTED == $response['status']) {
                $result = preg_preg_split("/\s/", $response['message']);

                return [
                    'count' => $result[0],
'start_id' => $result[1],
'end_id' => $result[2],
'group' => $result[3],
                ];
            }
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return null;
    }

    public function get_article_list($group)
    {
        if ($this->nntp) {
            $buf = $this->send_request('listgroup ' . $group);

            $response = $this->parse_response($buf);

            if (GROUP_SELECTED == $response['status']) {
                $body = $this->read_response_body();

                return explode("\r\n", mb_substr($body, 0, -2)); // Cut the last \r\n
            }
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return false;
    }

    public function get_group_list($group_pattern)
    {
        $response = $this->parse_response($this->send_request('list active ' . $group_pattern));

        if (INFORMATION_FOLLOWS == $response['status']) {
            $result = [];

            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                [$group, $last, $first, $post] = preg_preg_split("/\s+/", $buf, 4);

                $result[] = $group;

                $buf = fgets($this->nntp, 4096);
            }

            return $result;
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return false;
    }

    // The $group can have wildcard like comp.lang.*

    public function get_groups_description($groups)
    {
        $response = $this->parse_response($this->send_request('list newsgroups ' . $groups));

        if (INFORMATION_FOLLOWS == $response['status']) {
            $result = [];

            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                [$key, $value] = preg_preg_split("/\s+/", $buf, 2);

                $result[$key] = trim($value);

                $buf = fgets($this->nntp, 4096);
            }

            return $result;
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return false;
    }

    public function get_message_summary($start_id, $end_id)
    {
        $buf = $this->send_request('xover ' . $start_id . '-' . $end_id);

        $response = $this->parse_response($buf);

        $message_tree_root = new MessageTreeNode(null);

        $message_tree_root->set_show_children(true);

        $ref_list = [];

        if (ARTICLE_OVERVIEW == $response['status']) {
            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                $elements = preg_preg_split("/\t/", $buf);

                $message_info = new MessageInfo();

                $message_info->nntp_message_id = $elements[0];

                $message_info->subject = decode_MIME_header($elements[1]);

                $message_info->from = decode_sender(decode_MIME_header($elements[2]));

                $message_info->date = strtotime($elements[3]);

                if (-1 == $message_info->date) {
                    $message_info->date = $elements[3];
                }

                $message_info->message_id = $elements[4];

                if (0 != mb_strlen($elements[5])) {
                    $message_info->references = preg_preg_split("/\s+/", trim($elements[5]));
                } else {
                    $message_info->references = [];
                }

                $message_info->byte_count = $elements[6];

                $message_info->line_count = $elements[7];

                $message_tree_root->insert_message_info($message_info);

                $ref_list[$message_info->nntp_message_id] = [$message_info->message_id, $message_info->references];

                $buf = fgets($this->nntp, 4096);
            }

            return [$message_tree_root, $ref_list];
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return null;
    }

    // Similar to the get_message_summary function, except that the processing is much

    // lightweight with the return is just an array of message summaries instead of

    // a tree plus a reference list.

    public function get_summary($start_id, $end_id)
    {
        $buf = $this->send_request('xover ' . $start_id . '-' . $end_id);

        $response = $this->parse_response($buf);

        if (ARTICLE_OVERVIEW == $response['status']) {
            $buf = fgets($this->nntp, 4096);

            $result = [];

            while (!preg_match("/^\.\s*$/", $buf)) {
                $elements = preg_preg_split("/\t/", $buf);

                $nntp_id = $elements[0];

                $result[$nntp_id]['subject'] = decode_MIME_header($elements[1]);

                $from = decode_sender(decode_MIME_header($elements[2]));

                $result[$nntp_id]['from_name'] = $from['name'];

                $result[$nntp_id]['from_email'] = $from['email'];

                $result[$nntp_id]['date'] = strtotime($elements[3]);

                if (-1 == $result[$nntp_id]['date']) {
                    $result[$nntp_id]['date'] = $elements[3];
                }

                $result[$nntp_id]['message_id'] = $elements[4];

                $result[$nntp_id]['references'] = trim($elements[5]);

                $result[$nntp_id]['byte_count'] = $elements[6];

                $result[$nntp_id]['line_count'] = $elements[7];

                $buf = fgets($this->nntp, 4096);
            }

            return $result;
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return null;
    }

    public function get_header($message_id)
    {
        $response = $this->parse_response($this->send_request('head ' . $message_id));

        if ((ARTICLE_HEAD == $response['status']) || (ARTICLE_HEAD_BODY == $response['status'])) {
            $header = '';

            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                $header .= $buf;

                $buf = fgets($this->nntp, 4096);
            }

            return new MIME_message($header);
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return null;
    }

    public function get_article($message_id)
    {
        $response = $this->parse_response($this->send_request('article ' . $message_id));

        if ((ARTICLE_BODY == $response['status']) || (ARTICLE_HEAD_BODY == $response['status'])) {
            $message = '';

            $buf = fgets($this->nntp, 4096);

            while (!preg_match("/^\.\s*$/", $buf)) {
                $message .= $buf;

                $buf = fgets($this->nntp, 4096);
            }

            return new MIME_Message($message);
        }

        $this->error_number = $response['status'];

        $this->error_message = $response['message'];

        return null;
    }

    public function post_article($subject, $name, $email, $newsgroups, $references, $message, $files)
    {
        global $messages_ini;

        $from = encode_MIME_header($name) . ' <' . $email . '>';

        $groups = '';

        foreach ($newsgroups as $news) {
            $groups .= ',' . $news;
        }

        $groups = mb_substr($groups, 1);

        $current_time = date('D, d M Y H:i:s O', time());

        if (0 != mb_strlen($groups)) {
            $response = $this->parse_response($this->send_request('post'));

            if (ARTICLE_POST_READY == $response['status']) {
                $send_message = '';

                // Send the header

                $send_message .= 'Subject: ' . encode_MIME_header($subject) . "\r\n";

                $send_message .= 'From: ' . $from . "\r\n";

                $send_message .= 'Newsgroups: ' . $groups . "\r\n";

                $send_message .= 'Date: ' . $current_time . "\r\n";

                $send_message .= "User-Agent: Web-News v.1.5.7 (by Terence Yim)\r\n";

                $send_message .= "Mime-Version: 1.0\r\n";

                if (0 != count($files)) { // Handling uploaded files
                    mt_srand();

                    $boundary = '----------' . mt_rand() . time();

                    $send_message .= 'Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n";

                    $boundary = '--' . $boundary;
                } else {
                    $boundary = '';

                    $send_message .= "Content-Type: text/plain\r\n";
                }

                if ($references && (0 != mb_strlen($references))) {
                    $send_message .= 'References: ' . $references . "\r\n";
                }

                $send_message .= "\r\n"; // Header body separator

                $send_message .= create_message_body($message . ' ' . $messages_ini['text']['signature'], $files, $boundary);

                // Send the body

                fwrite($this->nntp, $send_message);

                $response = $this->parse_response($this->send_request("\r\n."));

                if (ARTICLE_POST_OK == $response['status']) {
                    // Return the message sent with all the attachments stripped
                    if (0 != count($files)) { // There is attachment, strip it
                        $len = mb_strpos($send_message, $boundary, mb_strpos($send_message, $boundary) + mb_strlen($boundary));

                        $send_message = mb_substr($send_message, 0, $len);

                        $send_message .= "\r\n";

                        $send_message .= count($files);

                        $send_message .= $messages_ini['text']['post_attachments'];

                        $send_message .= "\r\n" . $boundary . '--';
                    }

                    return new MIME_Message($send_message);
                }  

                $send_message = 'No article posted.';
            }
        }

        return null;
    }

    public function get_error_number()
    {
        return $this->error_number;
    }

    public function get_error_message()
    {
        return $this->error_message;
    }
}

class MessageInfo
{
    public $nntp_message_id;

    public $subject;

    public $from;

    public $date;

    public $message_id;

    public $references;

    public $byte_count;

    public $line_count;
}

class MessageTreeNode
{
    public $message_info;

    public $children;

    public $show_children;

    public function __construct($message_info)
    {
        $this->message_info = $message_info;

        $this->children = [];

        $this->show_children = false;
    }

    public function set_show_children($show)
    {
        $this->show_children = $show;
    }

    public function set_show_all_children($show)
    {
        $this->set_show_children($show);

        $keys = $this->get_children_keys();

        foreach ($keys as $key) {
            $child = &$this->get_child($key);

            $child->set_show_all_children($show);
        }
    }

    public function is_show_children()
    {
        return $this->show_children;
    }

    public function set_message_info($message_info)
    {
        $this->message_info = $message_info;
    }

    public function get_message_info()
    {
        return $this->message_info;
    }

    public function set_child($key, $child)
    {
        $this->children[$key] = $child;
    }

    public function get_child($key)
    {
        return $this->children[$key] ?? null;
    }

    public function count_children()
    {
        return count($this->children);
    }

    public function get_children_keys()
    {
        return array_keys($this->children);
    }

    public function get_children($start = 0, $length = -1)
    {
        if (-1 == $length) {
            return array_slice($this->children, $start);
        }

        return array_slice($this->children, $start, $length);
    }

    public function insert_message_info($message_info)
    {
        $node = &$this;

        foreach ($message_info->references as $ref_no) {
            $tmpnode = &$node->get_child($ref_no);

            if (null != $tmpnode) {
                $node = &$tmpnode;
            } else {
                $tmp_info = new MessageInfo();

                $tmp_info->nntp_message_id = -1;

                $tmp_info->message_id = $ref_no;

                $tmp_info->date = 0;

                $newnode = new self($tmp_info);

                $node->set_child($ref_no, $newnode);

                $node = &$node->get_child($ref_no);
            }
        }

        $child = &$node->get_child($message_info->message_id);

        if (null === $child) {
            $child = new self($message_info);
        } else {
            $child->set_message_info($message_info);
        }

        $node->set_child($message_info->message_id, $child);
    }

    public function merge_tree($root_node)
    {
        // If 2 children have the same key, the new one will replace the current one

        $keys = $root_node->get_children_keys();

        foreach ($keys as $key) {
            $child = &$root_node->get_child($key);

            $message_info = $child->get_message_info();

            $ref_list = $message_info->references;

            $node = &$this;

            if (0 != count($ref_list)) {
                foreach ($ref_list as $ref) {
                    $tmp = &$node->get_child($ref);

                    if (null != $tmp) {
                        $node = &$tmp;
                    }
                }
            }

            $node->set_child($key, $child);
        }
    }

    public function compact_tree()
    {
        $children_keys = $this->get_children_keys();

        foreach ($children_keys as $child_key) {
            $child = &$this->get_child($child_key);

            $child->compact_tree();

            $info = $child->get_message_info();

            if (-1 == $info->nntp_message_id) {
                // Need to remove this child and promote it's children

                $keys = $child->get_children_keys();

                foreach ($keys as $key) {
                    $tmp_node = &$child->get_child($key);

                    $this->set_child($key, $tmp_node);
                }

                unset($this->children[$child_key]);
            }
        }
    }

    public function sort_message($field, $asc)
    {
        $function_name = 'compare_by_' . $field;

        if ($asc) {
            $function_name .= '_asc';
        } else {
            $function_name .= '_desc';
        }

        if (method_exists($this, $function_name)) {
            if (0 != count($this->children)) {
                uasort($this->children, [$this, $function_name]);
            }
        }
    }

    public function deep_sort_message($field, $asc)
    {
        $this->sort_message($field, $asc);

        if (0 != count($this->children)) {
            $keys = $this->get_children_keys();

            foreach ($keys as $key) {
                $child = &$this->get_child($key);

                $child->deep_sort_message($field, $asc);
            }
        }
    }

    public function compare_by_subject_asc($node_1, $node_2)
    {
        $subject_1 = $node_1->get_message_info();

        $subject_2 = $node_2->get_message_info();

        $subject_1 = $subject_1->subject;

        $subject_2 = $subject_2->subject;

        return strcasecmp($subject_1, $subject_2);
    }

    public function compare_by_subject_desc($node_1, $node_2)
    {
        $subject_1 = $node_1->get_message_info();

        $subject_2 = $node_2->get_message_info();

        $subject_1 = $subject_1->subject;

        $subject_2 = $subject_2->subject;

        return strcasecmp($subject_2, $subject_1);
    }

    public function compare_by_from_asc($node_1, $node_2)
    {
        $from_1 = $node_1->get_message_info();

        $from_2 = $node_2->get_message_info();

        $from_1 = $from_1->from['name'];

        $from_2 = $from_2->from['name'];

        return strcasecmp($from_1, $from_2);
    }

    public function compare_by_from_desc($node_1, $node_2)
    {
        $from_1 = $node_1->get_message_info();

        $from_2 = $node_2->get_message_info();

        $from_1 = $from_1->from['name'];

        $from_2 = $from_2->from['name'];

        return strcasecmp($from_2, $from_1);
    }

    public function compare_by_date_asc($node_1, $node_2)
    {
        $date_1 = $node_1->get_message_info();

        $date_2 = $node_2->get_message_info();

        $date_1 = $date_1->date;

        $date_2 = $date_2->date;

        if ($date_1 < $date_2) {
            return -1;
        } elseif ($date_1 > $date_2) {
            return 1;
        }

        return 0;
    }

    public function compare_by_date_desc($node_1, $node_2)
    {
        $date_1 = $node_1->get_message_info();

        $date_2 = $node_2->get_message_info();

        $date_1 = $date_1->date;

        $date_2 = $date_2->date;

        if ($date_1 > $date_2) {
            return -1;
        } elseif ($date_1 < $date_2) {
            return 1;
        }

        return 0;
    }
}
