<?php

class MailerCron {

    var $time_limit;
    static $instance;

    function __construct() {
        self::$instance = $this;

        $max_time = (int) (ini_get('max_execution_time') * 0.9);
        if ($max_time == 0) {
            $max_time = 180;
        }
        $this->time_limit = time() + $max_time;

        add_filter('cron_schedules', array($this, 'wp_cron_schedules'));

        add_action('mailer_send', array($this, 'send'));
        add_action('mailer_clean', array($this, 'clean'));
    }

    function wp_cron_schedules($schedules) {
        $schedules['mailer'] = array(
            'interval' => 300, // seconds
            'display' => 'Mailer'
        );
        return $schedules;
    }

    function check_transient($name, $time) {
        // To avoid sinchronized cron
        sleep(rand(0, 2));
        if (get_transient($name) !== false) {
            Mailer::$instance->log('Called too quickly');
            return false;
        }
        set_transient($name, 1, $time);
        return true;
    }

    function clean() {
        Mailer::$instance->log(__METHOD__);
        $files = glob(MAILER_SENT_DIR . '/*.txt');
        $time = time() - 3600 * 24 * 30; // 30 days in the past
        foreach ($files as &$file) {
            if (filemtime($file) < $time) {
                Mailer::$instance->log('Deleted by clean: ' . basename($file));
                @unlink($file);
            }
            if (time() > $this->time_limit) {
                Mailer::$instance->log('Timeout reached on clean process');
                return;
            }
        }
    }

    /** Sends emails on queue, called every 5 minutes, stopping if the maximum number of email per hour are reached. */
    function send() {
        global $wpdb;

        Mailer::$instance->log("send");

        if (!$this->check_transient('mailer', 180))
            return;

        $files = glob(MAILER_OUT_DIR . '/*.txt');
        if (count($files) == 0) {
            Mailer::$instance->log('No files to send');
            delete_transient('mailer');
            return;
        }

        @ini_set('memory_limit', '256M');

        require_once ABSPATH . WPINC . '/class-phpmailer.php';
        require_once ABSPATH . WPINC . '/class-smtp.php';

        sort($files);
        $now = time();
        $max = Mailer::$instance->get_option('max') / 12;

        foreach ($files as &$file) {
            Mailer::$instance->log('Analysing file ' . $file);
            list($priority, $time) = explode('-', basename($file), 2);
            if ($time > $now) {
                Mailer::$instance->log('In the future, exiting');
                //return;
            }

            //$phpmailer = new PHPMailer();
            $phpmailer = unserialize(file_get_contents($file));
            if (!$phpmailer) {
                Mailer::$instance->error(basename($file), null);
                rename($file, MAILER_ERROR_DIR . '/' . basename($file));
                continue;
            }

            Mailer::$instance->setup_phpmailer($phpmailer);

            try {
                $r = $phpmailer->Send();
            } catch (Exception $e) {
                Mailer::$instance->error(basename($file), null, $e->getMessage());
                rename($file, MAILER_ERROR_DIR . '/' . basename($file));
                continue;
            }

            if (!$r) {
                Mailer::$instance->error(basename($file), $phpmailer);
                Mailer::$instance->log('Send error: ' . $phpmailer->ErrorInfo);
                if (strpos($phpmailer->ErrorInfo, 'connect') !== false) {
                    Mailer::$instance->log('SMTP connection error, message not moved to error folder');
                } else {
                    rename($file, MAILER_ERROR_DIR . '/' . basename($file));
                }
            } else {
                rename($file, MAILER_SENT_DIR . '/' . basename($file));
                Mailer::$instance->log('Sent');
                if (--$max <= 0) {
                    Mailer::$instance->log('Emails limit reached');
                    break;
                }
            }

            if (time() > $this->time_limit) {
                Mailer::$instance->log('Timeout reached');
                delete_transient('mailer');
                return;
            }
        }
        delete_transient('mailer');
    }

}

new MailerCron();
