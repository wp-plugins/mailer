<?php

/*
  Plugin Name: Mailer
  Plugin URI: http://www.satollo.net/plugins/mailer
  Description: Mailer throttles emails sent by WordPress and its plugins to bypass provider's mails per hours limit.
  Version: 1.4.4
  Author: Stefano Lissa
  Author URI: http://www.satollo.net
  Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
 */

/*
  Copyright 2014 Stefano Lissa  (email : satollo@gmail.com)
 */

define('MAILER_VERSION', '1.4.4');

define('MAILER_DIR', WP_CONTENT_DIR . '/mailer');
define('MAILER_OUT_DIR', MAILER_DIR . '/out');
define('MAILER_SENT_DIR', MAILER_DIR . '/sent');
define('MAILER_ERROR_DIR', MAILER_DIR . '/error');
define('MAILER_LOG', true);
define('MAILER_OPTIONS', 'mailer');

define('MAILER_URL', WP_PLUGIN_URL . '/mailer');

class FakePHPMailer {

    var $real_phpmailer = null;

    function FakePHPMailer() {
        $this->real_phpmailer = $phpmailer;
    }

    function Send() {
        global $phpmailer;

        // Restores the real phpmailer so WordPress do not create a new object on subsequent wp_mail() calls.
        $phpmailer = $this->real_phpmailer;
        return true;
    }

}

$mailer = new Mailer();

class Mailer {

    /**
     * @var Mailer
     */
    static $instance;
    var $options = null;
    var $fake_phpmailer = null;
    var $error_file;
    var $priority = 0;
    var $group = '';
    var $time = 0;
    var $secret;

    function __construct() {
        self::$instance = $this;

        $this->error_file = MAILER_DIR . '/errors.txt';
        $this->priority = (int) $this->get_option('priority', 0);
        $this->time = time();
        $this->secret = get_option('comment_plus_secret');
        add_action('phpmailer_init', array(&$this, 'wp_phpmailer_init'), 1000);
    }

    function set($priority, $group = '', $time = 0) {
        $this->log("set: " . $priority . ' ' . $group . ' ' . $time);
        $this->priority = (int) $priority;
        $this->group = $group;
        if ($time > time())
            $this->time = $time;
        else
            $this->time = time();
    }

    function reset() {
        $this->priority = (int) $this->get_option('priority', 0);
        $this->group = '';
        $this->time = 0;
    }

    function wp_phpmailer_init(&$phpmailer) {

        // Real time sending...
        if ($this->priority == 0) {
            $this->log('Priority zero, send it directly');
            $this->setup_phpmailer($phpmailer);
            return;
        } else {
            $file = MAILER_OUT_DIR . '/' . $this->priority . '-' . $this->time . '-' . $this->group . '-' . rand(10000, 99999) . '.txt';
            while (file_exists($file)) {
                $this->log('Exists... ' . $file);
                $file = MAILER_OUT_DIR . '/' . $this->priority . '-' . $this->time . '-' . $this->group . '-' . rand(10000, 99999) . '.txt';
            }
            $this->log('Saved file ' . $file);
            file_put_contents($file, serialize($phpmailer));
        }

        // This to save the real mailer and avoid WordPress to build a new one every time
        if ($this->fake_phpmailer == null)
            $this->fake_phpmailer = new FakePHPMailer();

        $this->fake_phpmailer->real_phpmailer = $phpmailer;
        $phpmailer = $this->fake_phpmailer;
    }

    function log($text) {
        if ($this->get_option('log') == 0)
            return;

        if (is_array($text) || is_object($text))
            $text = print_r($text, true);

        $time = date('d-m-Y H:i:s ');
        @file_put_contents(WP_CONTENT_DIR . '/logs/mailer-' . $this->secret . '.txt', $time . $text . "\n", FILE_APPEND | FILE_TEXT);
    }

    function error($file, PHPMailer $phpmailer = null, $message = null) {
        $time = date('Y-m-d H:i:s ');
        if ($message == null)
            $message = ($phpmailer == null ? 'File format not valid' : $phpmailer->ErrorInfo);
        @file_put_contents($this->error_file, $time . ' ' . $file . ' ' . $message . "\n", FILE_APPEND | FILE_TEXT);
    }

    /** Returns plugin default options. */
    function get_defaults() {
        return array('log' => 0, 'max' => 400, 'priority' => 1, 'sender' => 'never', 'sender_email' => '', 'sender_name' => '',
            'MAILER_port' => 110, 'smtp_port' => 25);
    }

    function get_options() {
        if ($this->options == null) {
            $this->options = get_option(MAILER_OPTIONS, array());
            $this->options = array_merge($this->get_defaults(), array_filter($this->options));
        }
        return $this->options;
    }

    /** Returns a plugin option by name, falling back to plugin default option value, if one, or returning the default value. */
    function get_option($name, $default = null) {
        if ($this->options == null) {
            $this->options = get_option(MAILER_OPTIONS, array());
        }
        $return = $this->options[$name];
        if ($return == null)
            $return = $default;
        return $return;
    }

    /**
     * Configures the phpmailer with return path and SMTP if specified on
     * configurations.
     */
    function setup_phpmailer(PHPMailer &$phpmailer) {

        $phpmailer->set('exceptions', false);

        // Return path setting
        $return_path = $this->get_option('return_path');
        if (!empty($return_path)) {
            if (empty($phpmailer->Sender))
                $phpmailer->Sender = $return_path;
        }

        // SMTP configuration
        $host = $this->get_option('smtp_host');
        if (!empty($host)) {
            $user = $this->get_option('smtp_user');
            $pass = $this->get_option('smtp_pass');
            $port = $this->get_option('smtp_port');
            $this->log('Using SMTP');
            $phpmailer->Host = $host;
            $phpmailer->IsSMTP();
            if (!empty($port))
                $phpmailer->Port = (int) $port;

            if (!empty($user)) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $user;
                $phpmailer->Password = $pass;
            }
        }

        $sender_name = $this->get_option('sender_name');
        $sender_email = $this->get_option('sender_email');
        switch ($this->get_option('sender')) {

            case 'always':
                if (!empty($sender_email))
                    $phpmailer->From = $sender_email;
                if (!empty($sender_name))
                    $phpmailer->FromName = $sender_name;
                break;

            case 'default':
                if (strpos($phpmailer->From, 'wordpress@') !== false) {
                    if (!empty($sender_email))
                        $phpmailer->From = $sender_email;
                    if (!empty($sender_name))
                        $phpmailer->FromName = $sender_name;
                }
                break;
        }
    }

}

function mailer_set($priority = 0, $group = '', $time = 0) {
    global $mailer;
    $mailer->set($priority, $group, $time);
}

function mailer_reset() {
    global $mailer;
    $mailer->reset();
}

if (is_admin()) {
    include dirname(__FILE__) . '/admin.php';
}

if (defined('DOING_CRON') && DOING_CRON || is_admin()) {
    include dirname(__FILE__) . '/cron.php';
}
