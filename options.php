<?php
@include_once dirname(__FILE__) . '/controls.php';

$plugin = &$mailer;

$action = '';
if (isset($_REQUEST['act'])) $action = $_REQUEST['act'];

if (!empty($action) && !check_admin_referer('save')) die('Invalid call');

$options = get_option('mailer');

if (isset($_POST['send'])) {
    MailerCron::$instance->send();
}

if ($action == 'save') {
  $options = stripslashes_deep($_POST['options']);
  $options['log'] = (int) $options['log'];
  update_option('mailer', $options);
}

if ($action == 'test') {
  var_dump(wp_mail("sa-test@sendmail.net", "test", "questo è il testo del messaggio firmato!"));
  //var_dump(wp_mail("satollo@gmail.com", "test", "questo è il testo del messaggio firmato!"));
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
  foreach ($files as &$file)
    @unlink($file);
}

if (strpos($action, 'empty_error_dir_') === 0) {
  $name = substr($action, strlen('empty_error_dir_'));
  $files = glob(MAILER_ERROR_DIR . '/*-' . $name . '-*.txt');
  foreach ($files as &$file)
    @unlink($file);
}

if ($action == 'empty_sent_dir') {
  $files = glob(MAILER_SENT_DIR . '/*.txt');
  foreach ($files as &$file)
    @unlink($file);
}

if (strpos($action, 'empty_sent_dir_') === 0) {
  $name = substr($action, strlen('empty_sent_dir_'));
  $files = glob(MAILER_SENT_DIR . '/*-' . $name . '-*.txt');
  foreach ($files as &$file)
    @unlink($file);
}

if ($action == 'empty_out_dir') {
  $files = glob(MAILER_OUT_DIR . '/*.txt');
  foreach ($files as &$file)
    @unlink($file);
}

if (strpos($action, 'empty_out_dir_') === 0) {
  $name = substr($action, strlen('empty_out_dir_'));
  $files = glob(MAILER_OUT_DIR . '/*-' . $name . '-*.txt');
  foreach ($files as &$file)
    @unlink($file);
}

$mailer->options = $options;

$controls->options = $plugin->get_options();
?>
<script>
var tabs;
jQuery(document).ready(function(){
  jQuery(function() {
    tabs = jQuery("#tabs").tabs();
  });
});
</script>
<div class="wrap">

<div id="satollo-header">
        <a href="http://www.satollo.net/plugins/mailer" target="_blank">Get Help</a>
        <a href="http://www.satollo.net/forums" target="_blank">Forum</a>

        <form style="display: inline; margin: 0;" action="http://www.satollo.net/wp-content/plugins/newsletter/do/subscribe.php" method="post" target="_blank">
            Subscribe to satollo.net <input type="email" name="ne" required placeholder="Your email">
            <input type="hidden" name="nr" value="include-it">
            <input type="submit" value="Go">
        </form>
        <!--
        <a href="https://www.facebook.com/satollo.net" target="_blank"><img style="vertical-align: bottom" src="http://www.satollo.net/images/facebook.png"></a>
        -->
        <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5PHGDGNHAYLJ8" target="_blank"><img style="vertical-align: bottom" src="http://www.satollo.net/images/donate.png"></a>
        <a href="http://www.satollo.net/donations" target="_blank">Even <b>2$</b> helps: read more</a>
    </div>

  <h2>Mailer</h2>


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
        $mail->Port = (int) $plugin->get_option('smtp_port', 25);

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
        } else {
          echo 'OK';
        }
        ?>
      </p>
      <pre style="height: 300px; overflow: auto">
        <?php echo $debug; ?>
      </pre>
    </div>
  <?php } ?>

      <p>
        Check out my other useful plugins:<br>
        <a href="http://www.satollo.net/plugins/comment-plus?utm_source=hyper-cache&utm_medium=banner&utm_campaign=comment-plus" target="_blank"><img src="http://www.satollo.net/images/plugins/comment-plus-icon.png"></a>
        <a href="http://www.satollo.net/plugins/header-footer?utm_source=hyper-cache&utm_medium=banner&utm_campaign=header-footer" target="_blank"><img src="http://www.satollo.net/images/plugins/header-footer-icon.png"></a>
        <a href="http://www.satollo.net/plugins/include-me?utm_source=hyper-cache&utm_medium=banner&utm_campaign=include-me" target="_blank"><img src="http://www.satollo.net/images/plugins/include-me-icon.png"></a>
        <a href="http://www.thenewsletterplugin.com/?utm_source=hyper-cache&utm_medium=banner&utm_campaign=newsletter" target="_blank"><img src="http://www.satollo.net/images/plugins/newsletter-icon.png"></a>
        <a href="http://www.satollo.net/plugins/php-text-widget?utm_source=hyper-cache&utm_medium=banner&utm_campaign=php-text-widget" target="_blank"><img src="http://www.satollo.net/images/plugins/php-text-widget-icon.png"></a>
        <a href="http://www.satollo.net/plugins/hyper-cache?utm_source=hyper-cache&utm_medium=banner&utm_campaign=hyper-cache" target="_blank"><img src="http://www.satollo.net/images/plugins/hyper-cache-icon.png"></a>
    </p>

  <h3>Documentation</h3>
  <p>
    Please, before send support request be sure to read carefully the whole <a href="http://www.satollo.net/plugins/mailer">Mailer plugin documentation</a>. Thank you.
  </p>

  <form method="post" action="">
    <?php $controls->init(); ?>

    <div id="tabs">
      <ul>
        <li><a href="#tabs-1">Working status</a></li>
        <li><a href="#tabs-2">Configuration</a></li>
      </ul>

      <div id="tabs-1">
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
              $groups = MailerAdmin::$instance->get_groups(MAILER_OUT_DIR);
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
              $groups = MailerAdmin::$instance->get_groups(MAILER_SENT_DIR);
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
              $groups = MailerAdmin::$instance->get_groups(MAILER_ERROR_DIR);
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
      </div>


      <div id="tabs-2">
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
      </div>

    </div>
    <p>
      <?php $controls->button('save', 'Save'); ?>
      <?php $controls->button('reset', 'Reset'); ?>
    </p>

  </form>

</div>