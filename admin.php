<?php

class MailerAdmin {

    var $max_time = 0;
    /**
     * @var MailerAdmin
     */
    static $instance;

    function __construct() {
        self::$instance = $this;
        add_action('admin_init', array($this, 'hook_admin_init'));
        add_action('admin_menu', array($this, 'wp_admin_menu'));
        add_action('admin_head', array($this, 'hook_admin_head'));
        register_activation_hook('mailer/plugin.php', array($this, 'wp_activate'));
        register_deactivation_hook('mailer/plugin.php', array($this, 'wp_deactivate'));
    }

    function hook_admin_init() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'mailer/') === 0) {
            wp_enqueue_script('jquery-ui-tabs');
        }
    }

    function wp_admin_menu() {
        add_options_page('Mailer', 'Mailer', 'manage_options', basename(dirname(__FILE__)) . '/options.php');
    }

    function hook_admin_head() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'mailer/') === 0) {
            echo '<link type="text/css" rel="stylesheet" href="' . MAILER_URL . '/admin.css?' . MAILER_VERSION . '"/>';
        }
    }

    function wp_activate() {

        @mkdir(MAILER_DIR);
        @mkdir(MAILER_OUT_DIR);
        @mkdir(MAILER_SENT_DIR);
        @mkdir(MAILER_ERROR_DIR);
        @touch(MAILER_DIR . '/errors.txt');
        wp_mkdir_p(WP_CONTENT_DIR . '/logs');

        wp_clear_scheduled_hook('mailer_send');
        wp_clear_scheduled_hook('mailer_clean');
        wp_clear_scheduled_hook('mailer_bounce');
        // Old hook maybe still there...
        wp_clear_scheduled_hook('mailer_cron');

        wp_schedule_event(time() + 900, 'mailer', 'mailer_send');
        wp_schedule_event(time() + 3600, 'daily', 'mailer_clean');
        //wp_schedule_event(time() + 14400, 'daily', 'mailer_bounce');
        // Useful only on very first activation: the last "false" means "do not load this option by default"
        add_option(MAILER_OPTIONS, array(), null, false);

        // May be it is useful to "array_filter" the stored options
        $options = get_option(MAILER_OPTIONS, array());
        // If there are new options with a default values, add it to stored options
        $options = array_merge(Mailer::$instance->get_defaults(), $options);
        update_option(MAILER_OPTIONS, $options);

        if (!get_option('comment-secret'))
            update_option('comment-secret', md5(rand(0, time())));

        wp_mkdir_p(WP_CONTENT_DIR . '/logs');
    }

    function wp_deactivate() {
        wp_clear_scheduled_hook('mailer_send');
        wp_clear_scheduled_hook('mailer_clean');
        wp_clear_scheduled_hook('mailer_bounce');
    }
    function get_groups($dir) {
        $list = glob($dir . '/*.txt');

        $groups = array('' => 0);
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
}

new MailerAdmin();
