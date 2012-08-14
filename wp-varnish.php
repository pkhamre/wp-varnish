<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://github.com/pkhamre/wp-varnish
Version: 0.3
Author: <a href="http://github.com/pkhamre/">Pål-Kristian Hamre</a>
Description: A plugin for purging Varnish cache when content is published or edited.

Copyright 2010 Pål-Kristian Hamre  (email : post_at_pkhamre_dot_com)

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
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPVarnish {
  public $wpv_addr_optname;
  public $wpv_port_optname;
  public $wpv_secret_optname;
  public $wpv_timeout_optname;
  public $wpv_update_pagenavi_optname;
  public $wpv_update_commentnavi_optname;

  function WPVarnish() {
    global $post;

    $this->wpv_addr_optname = "wpvarnish_addr";
    $this->wpv_port_optname = "wpvarnish_port";
    $this->wpv_secret_optname = "wpvarnish_secret";
    $this->wpv_timeout_optname = "wpvarnish_timeout";
    $this->wpvarnish_purge_url_optname = "wpvarnish_purge_url";
    $this->wpv_update_pagenavi_optname = "wpvarnish_update_pagenavi";
    $this->wpv_update_commentnavi_optname = "wpvarnish_update_commentnavi";
    $this->wpv_use_adminport_optname = "wpvarnish_use_adminport";
    $this->wpv_vversion_optname = "wpvarnish_vversion";
    $wpv_addr_optval = array ("127.0.0.1");
    $wpv_port_optval = array (80);
    $wpv_secret_optval = array ("");
    $wpv_timeout_optval = 5;
    $wpv_update_pagenavi_optval = 0;
    $wpv_update_commentnavi_optval = 0;
    $wpv_use_adminport_optval = 0;
    $wpv_vversion_optval = 2;

    if ( (get_option($this->wpv_addr_optname) == FALSE) ) {
      add_option($this->wpv_addr_optname, $wpv_addr_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_port_optname) == FALSE) ) {
      add_option($this->wpv_port_optname, $wpv_port_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_secret_optname) == FALSE) ) {
      add_option($this->wpv_secret_optname, $wpv_secret_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_timeout_optname) == FALSE) ) {
      add_option($this->wpv_timeout_optname, $wpv_timeout_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_update_pagenavi_optname) == FALSE) ) {
      add_option($this->wpv_update_pagenavi_optname, $wpv_update_pagenavi_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_update_commentnavi_optname) == FALSE) ) {
      add_option($this->wpv_update_commentnavi_optname, $wpv_update_commentnavi_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_use_adminport_optname) == FALSE) ) {
      add_option($this->wpv_use_adminport_optname, $wpv_use_adminport_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_vversion_optname) == FALSE) ) {
      add_option($this->wpv_vversion_optname, $wpv_vversion_optval, '', 'yes');
    }

    // Localization init
    add_action('init', array($this, 'WPVarnishLocalization'));

    // Add Administration Interface
    add_action('admin_menu', array($this, 'WPVarnishAdminMenu'));

    // When posts/pages are published, edited or deleted
    add_action('edit_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('edit_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    add_action('transition_post_status', array($this, 'WPVarnishPurgePostStatus'), 99, 3);
    add_action('transition_post_status', array($this, 'WPVarnishPurgeCommonObjectsStatus'), 99, 3);

    // When comments are made, edited or deleted
    add_action('comment_post', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('edit_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('trashed_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('untrashed_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('deleted_comment', array($this, 'WPVarnishPurgePostComments'),99);

    // When posts or pages are deleted
    add_action('deleted_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('deleted_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);

    // When xmlRPC call is made
    add_action('xmlrpc_call',array($this, 'WPVarnishPurgeAll'), 99);
  }

  function WPVarnishLocalization() {
    load_plugin_textdomain('wp-varnish', false, dirname(plugin_basename( __FILE__ ) ) . '/lang/');
  }

  //wrapper on WPVarnishPurgeCommonObjects for transition_post_status
  function WPVarnishPurgeCommonObjectsStatus($old, $new, $p) {
	  $this->WPVarnishPurgeCommonObjects($p->ID);
  }
  function WPVarnishPurgeCommonObjects() {
    $this->WPVarnishPurgeObject("/");
    $this->WPVarnishPurgeObject("/feed/");
    $this->WPVarnishPurgeObject("/feed/atom/");
    $this->WPVarnishPurgeObject("/category/(.*)");

    // Also purges page navigation
    if (get_option($this->wpv_update_pagenavi_optname) == 1) {
       $this->WPVarnishPurgeObject("/page/(.*)");
    }
  }

  // WPVarnishPurgeAll - Using a regex, clear all blog cache. Use carefully.
  function WPVarnishPurgeAll() {
    $this->WPVarnishPurgeObject('/(.*)');
  }

  // WPVarnishPurgeURL - Using a URL, clear the cache
  function WPVarnishPurgeURL($wpv_purl) {
    $wpv_purl = str_replace(get_bloginfo('url'),"",$wpv_purl);
    $this->WPVarnishPurgeObject($wpv_purl);
  }

  //wrapper on WPVarnishPurgePost for transition_post_status
  function WPVarnishPurgePostStatus($old, $new, $p) {
	  $this->WPVarnishPurgePost($p->ID);
  }
  // WPVarnishPurgePost - Takes a post id (number) as an argument and generates
  // the location path to the object that will be purged based on the permalink.
  function WPVarnishPurgePost($wpv_postid) {
    $wpv_url = get_permalink($wpv_postid);
    $wpv_permalink = str_replace(get_bloginfo('url'),"",$wpv_url);

    $this->WPVarnishPurgeObject($wpv_permalink);
  }

  // WPVarnishPurgePostComments - Purge all comments pages from a post
  function WPVarnishPurgePostComments($wpv_commentid) {
    $comment = get_comment($wpv_commentid);
    $wpv_commentapproved = $comment->comment_approved;

    // If approved or deleting...
    if ($wpv_commentapproved == 1 || $wpv_commentapproved == 'trash') {
       $wpv_postid = $comment->comment_post_ID;

       // Popup comments
       $this->WPVarnishPurgeObject('/\\\?comments_popup=' . $wpv_postid);

       // Also purges comments navigation
       if (get_option($this->wpv_update_commentnavi_optname) == 1) {
          $this->WPVarnishPurgeObject('/\\\?comments_popup=' . $wpv_postid . '&(.*)');
       }

    }
  }

  function WPVarnishAdminMenu() {
    if (!defined('VARNISH_HIDE_ADMINMENU')) {
      add_options_page(__('WP-Varnish Configuration','wp-varnish'), 'WP-Varnish', 1, 'WPVarnish', array($this, 'WPVarnishAdmin'));
    }
  }

  // WpVarnishAdmin - Draw the administration interface.
  function WPVarnishAdmin() {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
       if (current_user_can('administrator')) {
          if (isset($_POST['wpvarnish_admin'])) {
             if (!empty($_POST["$this->wpv_addr_optname"])) {
                $wpv_addr_optval = $_POST["$this->wpv_addr_optname"];
                update_option($this->wpv_addr_optname, $wpv_addr_optval);
             }

             if (!empty($_POST["$this->wpv_port_optname"])) {
                $wpv_port_optval = $_POST["$this->wpv_port_optname"];
                update_option($this->wpv_port_optname, $wpv_port_optval);
             }

             if (!empty($_POST["$this->wpv_secret_optname"])) {
                $wpv_secret_optval = $_POST["$this->wpv_secret_optname"];
                update_option($this->wpv_secret_optname, $wpv_secret_optval);
             }

             if (!empty($_POST["$this->wpv_timeout_optname"])) {
                $wpv_timeout_optval = $_POST["$this->wpv_timeout_optname"];
                update_option($this->wpv_timeout_optname, $wpv_timeout_optval);
             }

             if (!empty($_POST["$this->wpv_update_pagenavi_optname"])) {
                update_option($this->wpv_update_pagenavi_optname, 1);
             } else {
                update_option($this->wpv_update_pagenavi_optname, 0);
             }

             if (!empty($_POST["$this->wpv_update_commentnavi_optname"])) {
                update_option($this->wpv_update_commentnavi_optname, 1);
             } else {
                update_option($this->wpv_update_commentnavi_optname, 0);
             }

             if (!empty($_POST["$this->wpv_use_adminport_optname"])) {
                update_option($this->wpv_use_adminport_optname, 1);
             } else {
                update_option($this->wpv_use_adminport_optname, 0);
             }

             if (!empty($_POST["$this->wpv_vversion_optname"])) {
                $wpv_vversion_optval = $_POST["$this->wpv_vversion_optname"];
                update_option($this->wpv_vversion_optname, $wpv_vversion_optval);
             }

          }

          if (isset($_POST['wpvarnish_purge_url_submit'])) {
              $this->WPVarnishPurgeURL($_POST["$this->wpvarnish_purge_url_optname"]);
          }

          if (isset($_POST['wpvarnish_clear_blog_cache']))
             $this->WPVarnishPurgeAll();

          ?><div class="updated"><p><?php echo __('Settings Saved!','wp-varnish' ); ?></p></div><?php
       } else {
          ?><div class="updated"><p><?php echo __('You do not have the privileges.','wp-varnish' ); ?></p></div><?php
       }
    }

         $wpv_timeout_optval = get_option($this->wpv_timeout_optname);
         $wpv_update_pagenavi_optval = get_option($this->wpv_update_pagenavi_optname);
         $wpv_update_commentnavi_optval = get_option($this->wpv_update_commentnavi_optname);
         $wpv_use_adminport_optval = get_option($this->wpv_use_adminport_optname);
         $wpv_vversion_optval = get_option($this->wpv_vversion_optname);
    ?>
    <div class="wrap">
      <script type="text/javascript" src="<?php echo plugins_url('wp-varnish.js', __FILE__ ); ?>"></script>
      <h2><?php echo __("WordPress Varnish Administration",'wp-varnish'); ?></h2>
      <h3><?php echo __("IP address and port configuration",'wp-varnish'); ?></h3>
      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
    <?php
          // Can't be edited - already defined in wp-config.php
          global $varnish_servers;
          if (is_array($varnish_servers)) {
             echo "<p>" . __("These values can't be edited since there's a global configuration located in <em>wp-config.php</em>. If you want to change these settings, please update the file or contact the administrator.",'wp-varnish') . "</p>\n";
             // Also, if defined, show the varnish servers configured (VARNISH_SHOWCFG)
             if (defined('VARNISH_SHOWCFG')) {
                echo "<h3>" . __("Current configuration:",'wp-varnish') . "</h3>\n";
                echo "<ul>";
                foreach ($varnish_servers as $server) {
                   list ($host, $port, $secret) = explode(':', $server);
                   echo "<li>" . __("Server: ",'wp-varnish') . $host . "<br/>" . __("Port: ",'wp-varnish') . $port . "</li>";
                }
                echo "</ul>";
             }
          } else {
          // If not defined in wp-config.php, use individual configuration.
    ?>
       <!-- <table class="form-table" id="form-table" width=""> -->
       <table class="form-table" id="form-table">
        <tr valign="top">
            <th scope="row"><?php echo __("Varnish Administration IP Address",'wp-varnish'); ?></th>
            <th scope="row"><?php echo __("Varnish Administration Port",'wp-varnish'); ?></th>
            <th scope="row"><?php echo __("Varnish Secret",'wp-varnish'); ?></th>
        </tr>
        <script>
        <?php
          $addrs = get_option($this->wpv_addr_optname);
          $ports = get_option($this->wpv_port_optname);
          $secrets = get_option($this->wpv_secret_optname);
          echo "rowCount = $i\n";
          for ($i = 0; $i < count ($addrs); $i++) {
             // let's center the row creation in one spot, in javascript
             echo "addRow('form-table', $i, '$addrs[$i]', $ports[$i], '$secrets[$i]');\n";
        } ?>
        </script>
	</table>

      <br/>

      <table>
        <tr>
          <td colspan="3"><input type="button" class="" name="wpvarnish_admin" value="+" onclick="addRow ('form-table', rowCount)" /> <?php echo __("Add one more server",'wp-varnish'); ?></td>
        </tr>
      </table>
      <?php
         }
      ?>
      <p><?php echo __("Timeout",'wp-varnish'); ?>: <input class="small-text" type="text" name="wpvarnish_timeout" value="<?php echo $wpv_timeout_optval; ?>" /> <?php echo __("seconds",'wp-varnish'); ?></p>

      <p><input type="checkbox" name="wpvarnish_use_adminport" value="1" <?php if ($wpv_use_adminport_optval == 1) echo 'checked '?>/> <?php echo __("Use admin port instead of PURGE method.",'wp-varnish'); ?></p>

      <p><input type="checkbox" name="wpvarnish_update_pagenavi" value="1" <?php if ($wpv_update_pagenavi_optval == 1) echo 'checked '?>/> <?php echo __("Also purge all page navigation (experimental, use carefully, it will include a bit more load on varnish servers.)",'wp-varnish'); ?></p>

      <p><input type="checkbox" name="wpvarnish_update_commentnavi" value="1" <?php if ($wpv_update_commentnavi_optval == 1) echo 'checked '?>/> <?php echo __("Also purge all comment navigation (experimental, use carefully, it will include a bit more load on varnish servers.)",'wp-varnish'); ?></p>

      <p>Varnish Version: <select name="wpvarnish_vversion"><option value="2" <?php if ($wpv_vversion_optval == 2) echo 'selected '?>/> 2 </option><option value="3" <?php if ($wpv_vversion_optval == 3) echo 'selected '?>/> 3 </option></select></p>

      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __("Save Changes",'wp-varnish'); ?>" /></p>

      <p>
        Purge a URL:<input class="text" type="text" name="wpvarnish_purge_url" value="<?php echo get_bloginfo('url'); ?>" />
        <input type="submit" class="button-primary" name="wpvarnish_purge_url_submit" value="<?php echo __("Purge",'wp-varnish'); ?>" />
      </p>

      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_clear_blog_cache" value="<?php echo __("Purge All Blog Cache",'wp-varnish'); ?>" /> <?php echo __("Use only if necessary, and carefully as this will include a bit more load on varnish servers.",'wp-varnish'); ?></p>
      </form>
    </div>
  <?php
  }

  // WPVarnishPurgeObject - Takes a location as an argument and purges this object
  // from the varnish cache.
  function WPVarnishPurgeObject($wpv_url) {
    global $varnish_servers;

    if (is_array($varnish_servers)) {
       foreach ($varnish_servers as $server) {
          list ($host, $port) = explode(':', $server);
          $wpv_purgeaddr[] = $host;
          $wpv_purgeport[] = $port;
       }
    } else {
       $wpv_purgeaddr = get_option($this->wpv_addr_optname);
       $wpv_purgeport = get_option($this->wpv_port_optname);
       $wpv_secret = get_option($this->wpv_secret_optname);
    }

    $wpv_timeout = get_option($this->wpv_timeout_optname);
    $wpv_use_adminport = get_option($this->wpv_use_adminport_optname);
    $wpv_vversion_optval = get_option($this->wpv_vversion_optname);

    $wpv_wpurl = get_bloginfo('url');
    $wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
    $wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
    $wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
    $wpv_url = $wpv_blogaddr . $wpv_url;

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, $wpv_timeout);
      if (!$varnish_sock) {
        error_log("wp-varnish error: $errstr ($errno)");
        return;
      }

      if($wpv_use_adminport) {
        $buf = fread($varnish_sock, 1024);
        if(preg_match('/(\w+)\s+Authentication required./', $buf, $matches)) {
          # get the secret
          $secret = $wpv_secret[$i];
          fwrite($varnish_sock, "auth " . $this->WPAuth($matches[1], $secret) . "\n");
	  $buf = fread($varnish_sock, 1024);
          if(!preg_match('/^200/', $buf)) {
            error_log("wp-varnish error: authentication failed using admin port");
	    fclose($varnish_sock);
	    return;
	  }
        }
        if ($wpv_vversion_optval == 3) {
            $out = "ban req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
          } else {
            $out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
          }
      } else {
        $out = "PURGE $wpv_url HTTP/1.0\r\n";
        $out .= "Host: $wpv_host\r\n";
        $out .= "Connection: Close\r\n\r\n";
      }
      fwrite($varnish_sock, $out);
      fclose($varnish_sock);
    }
  }

  function WPAuth($challenge, $secret) {
    $ctx = hash_init('sha256');
    hash_update($ctx, $challenge);
    hash_update($ctx, "\n");
    hash_update($ctx, $secret . "\n");
    hash_update($ctx, $challenge);
    hash_update($ctx, "\n");
    $sha256 = hash_final($ctx);

    return $sha256;
  }
}

$wpvarnish = new WPVarnish();

?>
