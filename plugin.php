<?php

/*
  Plugin Name: Mailer
  Plugin URI: http://www.satollo.net/plugins/mailer
  Description: Mailer throttles emails sent by WordPress and its plugins to bypass provider's mails per hours limit.
  Version: 1.1.0
  Author: Satollo
  Author URI: http://www.satollo.net
  Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
 */

/*
  Copyright 2010 Satollo  (email : satollo@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

define('MAILER_DIR', WP_CONTENT_DIR . '/mailer');
define('MAILER_OUT_DIR', MAILER_DIR . '/out');
define('MAILER_SENT_DIR', MAILER_DIR . '/sent');
define('MAILER_ERROR_DIR', MAILER_DIR . '/error');
define('MAILER_LOG', true);
define('MAILER_OPTIONS', 'mailer');

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

    var $options = null;
    var $fake_phpmailer = null;
    var $error_file;
    var $time_limit = 0;
    var $bounce_markers = array('550 RCPT TO', 'Final-Recipient: RFC822;', '554 delivery error');

    function __construct() {
        add_action('init', array(&$this, 'wp_init'));
        $this->error_file = MAILER_DIR . '/errors.txt';
        add_filter('site_transient_update_plugins', array(&$this, 'wp_site_transient_update_plugins'));
        $max_time = (int) (ini_get('max_execution_time') * 0.8);
        if ($max_time == 0)
            $max_time = 3600;
        $this->time_limit = time() + $max_time;

        register_activation_hook(__FILE__, array(&$this, 'wp_activate'));
        register_deactivation_hook(__FILE__, array(&$this, 'wp_deactivate'));
        add_filter('cron_schedules', array(&$this, 'wp_cron_schedules'));

        add_action('mailer_send', array(&$this, 'send'));
        add_action('mailer_clean', array(&$this, 'clean'));
        add_action('mailer_bounce', array(&$this, 'bounce'));
    }

    function wp_site_transient_update_plugins($value) {
        if (isset($value->response))
            unset($value->response['mailer/plugin.php']);
        return $value;
    }

    function wp_init() {
        add_action('admin_menu', array(&$this, 'wp_admin_menu'));
        add_action('phpmailer_init', array(&$this, 'wp_phpmailer_init'), 1000);
    }

    function wp_phpmailer_init(PHPMailer $phpmailer) {
        $time = time();
        $priority = (int) $this->get_option('priority', 0);
        $group = '';
        // Check email data to see if it's a real time email, if so force to zero (won't changed anymore)
        //if (strpos($phpmailer->From, 'wordpress@') !== false) $priority = 0;

        foreach ($phpmailer->CustomHeader as &$header) {
            if ($header[0] == 'X-MailerTime') {
                $time = (int) trim($header[1]);
                if ($time < time())
                    $time = time();
            }
            if ($header[0] == 'X-MailerPriority') {
                $priority = (int) trim($header[1]);
                if ($priority < 0 || $priority > 2)
                    $priority = 1;
            }
            if ($header[0] == 'X-MailerGroup') {
                $group = strtolower(trim($header[1])) . '-';
            }
        }

        // Real time sending...
        if ($priority == 0) {
            $this->setup_phpmailer($phpmailer);

            // No trace about real time mails
            return;
//            $r = $phpmailer->Send();
//            if (!$r) $this->error($phpmailer);
        } else {
            $file = MAILER_OUT_DIR . '/' . $priority . '-' . $time . '-' . $group . rand(10000, 99999) . '.txt';
            while (file_exists($file)) {
                $this->log('Exists... ' . $file);
                $file = MAILER_OUT_DIR . '/' . $priority . '-' . $time . '-' . $group . rand(10000, 99999) . '.txt';
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

    function wp_admin_menu() {
        add_options_page('Mailer', 'Mailer', 'manage_options', basename(dirname(__FILE__)) . '/options.php');
    }

    function log($text) {
        if ($this->get_option('log') == 0)
            return;

        $time = date('d-m-Y H:i:s ');
        file_put_contents(dirname(__FILE__) . '/log.txt', $time . $text . "\n", FILE_APPEND | FILE_TEXT);
    }

    function error($file, PHPMailer $phpmailer) {
        $time = date('Y-m-d H:i:s ');
        file_put_contents($this->error_file, $time . ' ' . $file . ' ' . $phpmailer->ErrorInfo . "\n", FILE_APPEND | FILE_TEXT);
    }

    /** Returns plugin default options. */
    function get_defaults() {
        return array('log' => 0, 'max' => 400, 'priority' => 1, 'sender' => 'never', 'sender_email' => '', 'sender_name' => '',
            'bounce_port' => 110, 'smtp_port' => 25);
    }

    function get_options() {
        if ($this->options == null) {
            $this->options = get_option(MAILER_OPTIONS, array());
            $this->options = array_merge($this->get_defaults(), array_filter($this->options));
        }
        return $this->options;
    }

    /** Returns a plugin option by name, falling back to plugin default option value, if one, or returning the default value. */
    function get_option($name, $default=null) {
        if ($this->options == null) {
            $this->options = get_option(MAILER_OPTIONS, array());
            $this->options = array_merge($this->get_defaults(), array_filter($this->options));
        }
        $return = $this->options[$name];
        if ($return == null)
            $return = $default;
        return $return;
    }

    function clean() {
        $this->log(__METHOD__);
        $files = glob(MAILER_SENT_DIR . '/*.txt');
        $time = time() - 3600 * 24 * 30; // 30 days in the past
        foreach ($files as &$file) {
            if (filemtime($file) < $time) {
                $this->log('Deleted by clean: ' . basename($file));
                @unlink($file);
            }
            if (time() > $this->time_limit) {
                $this->log('Timeout reached on clean process');
                return;
            }
        }
    }

    function bounce() {
        error_reporting(E_ALL);
        $this->log(__METHOD__);
        $host = $this->get_option('bounce_host');
        if (empty($host))
            return;

        @require_once ABSPATH . WPINC . '/class-pop3.php';

        $pop3 = new POP3();
        if (!$pop3->connect($this->get_option('bounce_host'), $this->get_option('bounce_port')) ||
                !$pop3->user($this->get_option('bounce_user'))) {
            $this->log($pop3->ERROR);
            return;
        }

        $count = $pop3->pass($this->get_option('bounce_pass'));

        if (false === $count) {
            $this->log($pop3->ERROR);
            return;
        }

        if (0 === $count) {
            $this->log('No messages');
            $pop3->quit();
            return;
        }

        $this->log('Messages: ' . $count);
        
        for ($i = 1; $i <= $count; $i++) {
            $message = $pop3->get($i);
            if (!$pop3->delete($i)) {
                $this->log('Unable to delete: ' . $pop3->ERROR);
                //$pop3->reset();
                //$pop3->quit();
                //return;
            }
            $message = implode('', $message);
            //$message = str_replace("\r", "\n", $message);
            $message = explode("\r\n", $message);

            $this->log('Message found ' . $i);
            $buffer = '';
            foreach ($message as &$line) {
                $this->log($line);
                $email = $this->bounce_check($line);
                if (!empty($email)) {
                    do_action('mailer_bounce_email', $email);
                    $this->log('Bounce email: ' . $email);
                }
            }
        }

        $pop3->quit();
        $this->log('close');
    }

    function bounce_check(&$line)
    {
        foreach($this->bounce_markers as $marker) {
            if (stripos($line, $marker) !== false) {
                return $this->extract_email($line);
            }
        }
        return null;
    }

    function extract_email(&$line) {
        $reg = '/[a-zA-Z0-9_\+\-\.]+@[a-zA-Z0-9_\+\-\.]+/i';
        $list = array();
        preg_match($reg, $line, $list);
        if (empty($list))
            return null;
        return $list[0];
    }

    /** Sends emails on queue, called every 5 minutes, stopping if the maximum number of email per hour are reached. */
    function send() {
        global $wpdb;
        $this->log(__METHOD__);

        $files = glob(MAILER_OUT_DIR . '/*.txt');
        if (count($files) == 0) {
            $this->log('No files to send');
            return;
        }

        @ini_set('memory_limit', '256M');

        require_once ABSPATH . WPINC . '/class-phpmailer.php';
        require_once ABSPATH . WPINC . '/class-smtp.php';

        sort($files);
        $now = time();
        $max = $this->get_option('max') / 12;

        foreach ($files as &$file) {
            $this->log('Analysing file ' . $file);
            list($priority, $time) = explode('-', basename($file), 2);
            if ($time > $now) {
                $this->log('In the future, exiting');
                //return;
            }

            //$phpmailer = new PHPMailer();
            $phpmailer = unserialize(file_get_contents($file));
            $this->setup_phpmailer($phpmailer);

            $r = $phpmailer->Send();
            if (!$r) {
                $this->error(basename($file), $phpmailer);
                $this->log('Send error: ' . $phpmailer->ErrorInfo);
                if (strpos($phpmailer->ErrorInfo, 'connect') !== false) {
                    $this->log('SMTP connection error, message not moved to error folder');
                } else {
                    rename($file, MAILER_ERROR_DIR . '/' . basename($file));
                }
            } else {
                rename($file, MAILER_SENT_DIR . '/' . basename($file));
                $this->log('Sent');
                if (--$max <= 0) {
                    $this->log('Emails limit reached');
                    break;
                }
            }

            if (time() > $this->time_limit) {
                $this->log('Timeout reached');
                return;
            }
        }
    }

    /**
     * Configures the phpmailer with return path and SMTP if specified on
     * configurations.
     */
    function setup_phpmailer(PHPMailer &$phpmailer) {

        // Return path setting
        $return_path = $this->get_option('return_path');
        if (!empty($return_path)) {
            if (empty($phpmailer->Sender)) $phpmailer->Sender = $return_path;
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

    function get_groups($dir) {
        $list = glob($dir . '/*.txt');
        $groups = array('' => count($list));
        if ($list) {
            foreach ($list as &$file) {
                $parts = explode('-', basename($file));
                if (count($parts) > 3) {
                    $group = $parts[2];
                    if (!isset($groups[$group]))
                        $groups[$group] = 1;
                    else
                        $groups[$group]++;
                }
            }
        }
        return $groups;
    }

    function wp_activate() {

        @mkdir(MAILER_DIR);
        @mkdir(MAILER_OUT_DIR);
        @mkdir(MAILER_SENT_DIR);
        @mkdir(MAILER_ERROR_DIR);
        @touch(MAILER_DIR . '/errors.txt');

        wp_schedule_event(time() + 900, 'mailer', 'mailer_send');
        wp_schedule_event(time() + 3600, 'daily', 'mailer_clean');
        wp_schedule_event(time() + 14400, 'daily', 'mailer_bounce');

        // Old hook maybe still there...
        wp_clear_scheduled_hook('mailer_cron');

        // Useful only on very first activation: the last "false" means "do not load this option by default"
        add_option(MAILER_OPTIONS, array(), null, false);

        // May be it is useful to "array_filter" the stored options
        $options = get_option(MAILER_OPTIONS, array());
        // If there are new options with a default values, add it to stored options
        $options = array_merge($this->get_defaults(), $options);
        update_option(MAILER_OPTIONS, $options);
    }

    function wp_deactivate() {
        wp_clear_scheduled_hook('mailer_send');
        wp_clear_scheduled_hook('mailer_clean');
        wp_clear_scheduled_hook('mailer_bounce');
    }

    function wp_cron_schedules($schedules) {
        $schedules['mailer'] = array(
            'interval' => 300, // seconds
            'display' => 'Mailer'
        );
        return $schedules;
    }

}
