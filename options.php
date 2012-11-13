<?php
@include_once dirname(__FILE__) . '/controls.php';

$plugin = &$mailer;

$action = $_REQUEST['act'];
if (isset($action) && !check_admin_referer())
    die('Invalid call');

$options = get_option('mailer');

if (isset($_POST['send'])) {
    $mailer->send();
}

if (isset($_POST['bounce'])) {
    $mailer->bounce();
}

if ($action == 'save') {
    $options = stripslashes_deep($_POST['options']);
    $options['log'] = (int)$options['log'];
    update_option('mailer', $options);
}

if ($action == 'reset') {
    $options = $mailer->get_defaults();
    update_option('mailer', $options);
}

if ($action == 'empty_error') {
    file_put_contents($plugin->error_file, '');
}

if ($action == 'empty_error_dir') {
    $files = glob(MAILER_ERROR_DIR . '/*.txt');
    foreach ($files as &$file) @unlink($file);
}

if (strpos($action, 'empty_error_dir_') === 0) {
    $name = substr($action, strlen('empty_error_dir_'));
    $files = glob(MAILER_ERROR_DIR . '/*-' . $name . '-*.txt');
    foreach ($files as &$file) @unlink($file);
}

if ($action == 'empty_sent_dir') {
    $files = glob(MAILER_SENT_DIR . '/*.txt');
    foreach ($files as &$file) @unlink($file);
}

if (strpos($action, 'empty_sent_dir_') === 0) {
    $name = substr($action, strlen('empty_sent_dir_'));
    $files = glob(MAILER_SENT_DIR . '/*-' . $name . '-*.txt');
    foreach ($files as &$file) @unlink($file);
}

if ($action == 'empty_out_dir') {
    $files = glob(MAILER_OUT_DIR . '/*.txt');
    foreach ($files as &$file) @unlink($file);
}

if (strpos($action, 'empty_out_dir_') === 0) {
    $name = substr($action, strlen('empty_out_dir_'));
    $files = glob(MAILER_OUT_DIR . '/*-' . $name . '-*.txt');
    foreach ($files as &$file) @unlink($file);
}

$mailer->options = $options;

$controls->options = $plugin->get_options();
?>
<style type="text/css">
    .form-table {
        background-color: #fff;
        border: 2px solid #eee;
    }
    .form-table th {
        text-align: right;
        font-weight: bold;
    }
    h3 {
        margin-bottom: 0;
        padding-bottom: 0;
        margin-top: 20px;
        font-size: 13px;
    }
    .grid {
        border-collapse: collapse;
    }
    .grid td, .grid th {
        padding: 10px;
        border: 1px solid #ddd;
        margin: 0;
    }
    .grid th {
        background-color: #aaa;
    }

    .gridh {
        margin-bottom: 25px;
        border: 1px solid #ddd;
    }
    .gridh th {
        width: 100px;
        vertical-align: middle;
    }

    .gridh td, .gridh th {
        padding: 3px;
        vertical-align: middle;
    }
</style>
<div class="wrap">
    <h2>Mailer</h2>
    <div class="updated">
        <p>
            This plugin is not free, you must have a membership subscription on
            <a href="http://www.satollo.net" target="_blank">www.satollo.net</a> (opens on a new window) to use it.
            If your membership is expired you can use this version for life.
        </p>
    </div>

    <?php if ($action == 'bounce_test') { ?>
    <div class="updated"><p>
        <strong>POP3 test connection report:</strong>
        <?php
        @require_once ABSPATH . WPINC . '/class-pop3.php';

        $pop3 = new POP3();
        if (!$pop3->connect($plugin->get_option('bounce_host'), $plugin->get_option('bounce_port')) ||
                !$pop3->user($plugin->get_option('bounce_user'))) {
            echo esc_html($pop3->ERROR);
        }
        else {
            $count = $pop3->pass($plugin->get_option('bounce_pass'));

            if (false === $count) {
                echo esc_html($pop3->ERROR);
            }
            else {
                echo "Connection ok, found " . $count . " messages.";
                $pop3->quit();
            }
        }
        ?>
        </p></div>
    <?php } ?>


    <?php if ($action == 'smtp_test') { ?>
    <div class="updated"><p>
        <strong>SMTP test connection report (sending an email to <?php echo get_option('admin_email'); ?>:</strong>
        <?php

        require_once ABSPATH . WPINC . '/class-phpmailer.php';
        require_once ABSPATH . WPINC . '/class-smtp.php';
        $mail = new PHPMailer();

        $mail->IsSMTP();
        $mail->SMTPDebug = true;

        $mail->CharSet = 'UTF-8';

        $mail->IsHTML(false);
        $mail->Body = 'Test email from Mailer plugin';

        $mail->From = $plugin->get_option('sender_email');
        $mail->FromName = $plugin->get_option('sender_name');
        $mail->Subject = '[' . get_option('blogname') . '] SMTP test from Mailer plugin';

        $mail->Host = $plugin->get_option('smtp_host');
        $mail->Port = (int)$plugin->get_option('smtp_port', 25);

        $user = $plugin->get_option('smtp_user');
        if (!empty($user)) {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $plugin->get_option('smtp_pass');
        }

        $mail->SMTPKeepAlive = false;

        $mail->ClearAddresses();
        $mail->AddAddress(get_option('admin_email'));
        ob_start();
        $mail->Send();
        $mail->SmtpClose();
        $debug = htmlspecialchars(ob_get_clean());
        ob_end_clean();

        if ($mail->IsError()) {
            echo $mail->ErrorInfo;
        }
        else {
            echo 'OK';
        }
    ?>
        </p>
        <pre style="height: 300px; overflow: auto">
        <?php echo $debug; ?>
        </pre>
    </div>
    <?php } ?>

    <h3>Documentation</h3>
    <p>
        Please, before send support request be sure to read carefully the whole <a href="http://www.satollo.net/plugins/mailer">Mailer plugin documentation</a>. Thank you.
    </p>
    <form method="post" action="">
        <?php $controls->init(); ?>
        <h3>System test and stats</h3>
        <table class="form-table">
            <tr valign="top">
                <th>System limits</th>
                <td>
                    PHP timeout: <?php echo ini_get('max_execution_time'); ?>
                    <br />
                    PHP memory limit: <?php echo ini_get('memory_limit'); ?>
                </td>
            </tr>
            <tr valign="top">
                <th>Installation status<br /><small>if empty it's ok</small></th>
                <td>
                    <?php if (!is_dir(MAILER_DIR)) { ?>wp-content/mailer folder is missing<br /><?php } ?>
                    <?php if (!is_dir(MAILER_OUT_DIR)) { ?>wp-content/mailer/out folder is missing<br /><?php } ?>
                    <?php if (!is_dir(MAILER_SENT_DIR)) { ?>wp-content/mailer/send folder is missing<br /><?php } ?>
                    <?php if (!is_dir(MAILER_ERROR_DIR)) { ?>wp-content/mailer/error folder is missing<br /><?php } ?>
                    <?php if (!touch(MAILER_DIR . '/errors.txt')) { ?>wp-content/mailer folder is not writable<br /><?php } ?>
                </td>
            </tr>
            <tr valign="top">
                <th>Mailer folders</th>
                <td>
                    <?php
                    $groups = $mailer->get_groups(MAILER_OUT_DIR);
                    ?>
                    Out
                    <table class="gridh">
                        <tr>
                            
                            <th>Total</th>
                            <td><?php echo $groups['']; ?></td>
                            <td><?php $controls->button('empty_out_dir', 'Delete all'); ?></td>
                        </tr>
                    <?php
                    foreach ($groups as $name => $value) {
                        if ($name == '') continue;
                        echo '<tr><th>' . $name . '</th><td>' . $value . '</td><td>';
                        echo $controls->button('empty_out_dir_' . $name, 'Delete all');
                        echo '</td></tr>';
                    }
                    ?>
                    </table>

                    <?php
                    $groups = $mailer->get_groups(MAILER_SENT_DIR);
                    ?>
                    Sent
                    <table class="gridh">
                        <tr>
                            <th>Total</th>
                            <td><?php echo $groups['']; ?></td>
                            <td><?php $controls->button('empty_sent_dir', 'Delete all'); ?></td>
                        </tr>
                    <?php
                    foreach ($groups as $name => $value) {
                        if ($name == '') continue;
                        echo '<tr><th>' . $name . '</th><td>' . $value . '</td><td>';
                        echo $controls->button('empty_sent_dir_' . $name, 'Delete all');
                        echo '</td></tr>';
                    }
                    ?>
                    </table>

                    <?php
                    $groups = $mailer->get_groups(MAILER_ERROR_DIR);
                    ?>
                    Error
                    <table class="gridh">
                        <tr>
                            <th>Total</th>
                            <td><?php echo $groups['']; ?></td>
                            <td><?php $controls->button('empty_error_dir', 'Delete all'); ?></td>
                        </tr>
                    <?php
                    foreach ($groups as $name => $value) {
                        if ($name == '') continue;
                        echo '<tr><th>' . $name . '</th><td>' . $value . '</td><td>';
                        echo $controls->button('empty_error_dir_' . $name, 'Delete all');
                        echo '</td></tr>';
                    }
                    ?>
                    </table>
   
                    <a href="<?php echo WP_CONTENT_URL; ?>/mailer/errors.txt" target="_blank">Open error file</a>
                    <?php $controls->button('empty_error', 'Empty error file'); ?>
                </td>
            </tr>
            <tr>
                <th>Scheduler</th>
                <td>
                    next run in <?php echo wp_next_scheduled('mailer_send') - time(); ?> seconds
                    <input type="submit" class="button-secondary" value="run now" name="send"/>
                </td>
            </tr>

        </table>

        <h3>Configuration</h3>
        <table class="form-table">
            <tr valign="top">
                <th>Enable logging?</th>
                <td>
                    <?php $controls->select('log', array(0 => 'No', 1 => 'Yes')); ?>
                    <br />
		    Logs will be written on "log.txt" file inside the plugin folder and can contain sensible information, use only for debug.
                </td>
            </tr>
            <tr valign="top">
                <th>Default priority</th>
                <td>
                    <?php $controls->select('priority', array(0 => 'Real time', 1 => 'Scheduled')); ?>
                    <br />
		    Priority to assign for messages that have not a "Mailer priority" (see documentation) specified. Usually all messages from WordPress and
		    plugins have not a priority, so choosing "Scheduled" enables throttling.
                </td>
            </tr>
            <tr valign="top">
                <th>Max emails per hour</th>
                <td>
                    <?php $controls->text('max', 6); ?>
                    <br />
		    Emails are sent every 5 minutes for a total of 16 batches per hour. See documentation about how to make WordPress cron work
			properly.
                </td>
            </tr>
            <tr valign="top">
                <th>Sender name and email</th>
                <td>
                    mode: <?php $controls->select('sender', array('never' => 'Never force sender data', 'always' => 'Always force sender data', 'default' => 'Preserve non default sender data')); ?>
                    <br />
                    name: <?php $controls->text('sender_name', 40); ?>
                    <br />
                    email: <?php $controls->text('sender_email', 40); ?>
                    <br />
                    Here you can set if and how to change the email sender address and name. Default sender is the one created by WordPress (eg. wordpress@yourdomain.com) but some plugin can change it, so you can decide 
		if force the sender data as specified above always, never or only when a default address was used to compose the email. If a filed is left blank, it won't be used regardless the "mode" selected.
                </td>
            </tr>
            <tr valign="top">
                <th>Return path</th>
                <td>
                    <?php $controls->text('return_path', 40); ?>
                    <br />
		    This must be a valid email address, optionally different from the sender email above, where "mail delivering errors" should be notified (by mail servers). If left blank the return path won't be set. Some provider
		    do not allow PHP script to set return path and can force it to different values or block outgoing emails.
                    <br />
                    If you configure the bounce management below, mails sent to this address must be readable with specified POP3 access.
                </td>
            </tr>
            <tr valign="top">
                <th>SMTP</th>
                <td>
                    host: <?php $controls->text('smtp_host', 50); ?>
                    port: <?php $controls->text('smtp_port', 5); ?>
                    <?php $controls->button('smtp_test', 'Test'); ?>

                    <br />
                    user: <?php $controls->text('smtp_user', 20); ?>
                            pass: <?php $controls->text('smtp_pass', 20); ?>
                            <br />
        		    Here you can configure an external SMTP service to send emails. If "host" is left blank, SMTP won't be used. If your SMTP service require authentication you must specify a username and a passoword
        		    otherwise left the two fields blank.
                </td>
            </tr>
        </table>

        <h3>Bounce management (experimental)</h3>
        <table class="form-table">
            <tr valign="top">
                <th>POP3</th>
                <td>
                    host: <?php $controls->text('bounce_host', 50); ?>
                    port: <?php $controls->text('bounce_port', 5); ?>
                    <?php $controls->button('bounce_test', 'Test'); ?>

                    <br />
                    user: <?php $controls->text('bounce_user', 20); ?>
                    pass: <?php $controls->text('bounce_pass', 20); ?>
                    <br />
                    If "host" is left blank, bounce won't be checked.
                </td>
            </tr>
            <tr valign="top">
                <th>Bounce checking</th>
                <td>
                    Next run on <?php echo wp_next_scheduled('mailer_bounce') - time(); ?> seconds
                    <input type="submit" class="button-secondary" value="run now" name="bounce"/>
                </td>
            </tr>
        </table>
        <p>
        <?php $controls->button('save', 'Save'); ?>
        <?php $controls->button('reset', 'Reset'); ?>
        </p>

        <h3>Debug information</h3>
        <table class="form-table">
            <tr valign="top">
                <th>wp_get_schedules</th>
                <td>
                    <?php var_dump(wp_get_schedules()); ?>
                </td>
            </tr>
            <tr valign="top">
                <th>_get_cron_array</th>
                <td>
                    <?php var_dump(_get_cron_array()); ?>
                </td>
            </tr>            
        </table>
    </form>

    <p></p>

</div>