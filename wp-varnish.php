<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://github.com/pkhamre/wp-varnish
Version: 0.2
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
  public $wpv_timeout;

  function WPVarnish() {
    global $post;

    $this->wpv_addr_optname = "wpvarnish_addr";
    $this->wpv_port_optname = "wpvarnish_port";
    $this->wpv_timeout_optname = "wpvarnish_timeout";
    $wpv_addr_optval = array ("127.0.0.1");
    $wpv_port_optval = array (80);
    $wpv_timeout_optval = 5;

    if ( (get_option($this->wpv_addr_optname) == FALSE) ) {
      add_option($this->wpv_addr_optname, $wpv_addr_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_port_optname) == FALSE) ) {
      add_option($this->wpv_port_optname, $wpv_port_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_timeout_optname) == FALSE) ) {
      add_option($this->wpv_timeout_optname, $wpv_timeout_optval, '', 'yes');
    }

    add_action('init', array(&$this, 'WPVarnishLocalization'));
    add_action('admin_menu', array(&$this, 'WPVarnishAdminMenu'));
    add_action('edit_post', array(&$this, 'WPVarnishPurgePost'), 99);
    add_action('edit_post', array(&$this, 'WPVarnishPurgeCommonObjects'), 99);
    add_action('deleted_post', array(&$this, 'WPVarnishPurgeCommonObjects'), 99);
    add_action('publish_post', array(&$this, 'WPVarnishPurgeCommonObjects'), 99);
  }

  function WPVarnishLocalization() {
    load_plugin_textdomain('wp-varnish',false,'wp-varnish/lang');
  }

  function WPVarnishPurgeCommonObjects() {
    $this->WPVarnishPurgeObject("/");
    $this->WPVarnishPurgeObject("/feed/");
    $this->WPVarnishPurgeObject("/feed/atom/");
  }

  function WPVarnishAdminMenu() {
    add_options_page('WPVarnish', 'WPVarnish', 1, 'WPVarnish', array(&$this, 'WPVarnishAdmin'));
  }

  // WpVarnishAdmin - Draw the administration interface.
  function WPVarnishAdmin() {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $wpv_addr_optval = $_POST["$this->wpv_addr_optname"];
      $wpv_port_optval = $_POST["$this->wpv_port_optname"];
      $wpv_timeout_optval = $_POST["$this->wpv_timeout_optname"];

      update_option($this->wpv_addr_optname, $wpv_addr_optval);
      update_option($this->wpv_port_optname, $wpv_port_optval);
      update_option($this->wpv_timeout_optname, $wpv_timeout_optval);
    }

    ?>
    <div class="wrap">
      <script type="text/javascript" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/wp-varnish/wp-varnish.js"></script>
      <h2><?php echo __("WordPress Varnish Administration",'wp-varnish'); ?></h2>
      <h3><?php echo __("IP address and port configuration",'wp-varnish'); ?></h3>
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
                   list ($host, $port) = explode(':', $server); 
                   echo "<li>" . __("Server: ",'wp-varnish') . $host . "<br/>" . __("Port: ",'wp-varnish') . $port . "</li>";
                }
                echo "</ul>";
             }
          } else {
          // If not defined in wp-config.php, use individual configuration.
    ?>
      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
       <!-- <table class="form-table" id="form-table" width=""> -->
       <table class="form-table" id="form-table">
        <tr valign="top">
            <th scope="row"><?php echo __("Varnish Administration IP Address",'wp-varnish'); ?></th>
            <th scope="row"><?php echo __("Varnish Administration Port",'wp-varnish'); ?></th>
        </tr>
        <script>
        <?php
          $addrs = get_option($this->wpv_addr_optname);
          $ports = get_option($this->wpv_port_optname);
          $timeout = get_option($this->wpv_timeout_optname);
          echo "rowCount = $i\n";
          for ($i = 0; $i < count ($addrs); $i++) {
             // let's center the row creation in one spot, in javascript
             echo "addRow('form-table', $i, '$addrs[$i]', $ports[$i]);\n";
        } ?>
        </script>
        <tr>
          <th class="th-full" colspan="3"><input type="button" class="" name="wpvarnish_admin" value="+" onclick="addRow ('form-table', rowCount)" /></th>
        </tr>
        <tr>
          <th class="th-full" colspan="3"><?php echo __("Timeout",'wp-varnish'); ?>: <input class="small-text" type="text" name="wpvarnish_timeout" value="<?php echo $timeout; ?>" /> <?php echo __("seconds",'wp-varnish'); ?></th>
        </tr>
      </table>


      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __("Save Changes",'wp-varnish'); ?>" /></p>
      </form>
      <?php
         }
      ?>
    </div>
  <?php
  }

  // WPVarnishPurgePost - Takes a post id (number) as an argument and generates
  // the location path to the object that will be purged based on the permalink.
  function WPVarnishPurgePost($wpv_postid) {
    global $varnish_servers;

    $varnish_url = get_permalink($wpv_postid);
    if (is_array($varnish_servers)) {
       foreach ($varnish_servers as $server) {
          list ($host, $port) = explode(':', $server);
          $wpv_purgeaddr[] = $host;
          $wpv_purgeport[] = $port;
       }
    } else {
       $wpv_purgeaddr = get_option($this->wpv_addr_optname);
       $wpv_purgeport = get_option($this->wpv_port_optname);
    }

    $wpv_timeout = get_option($this->wpv_timeout_optname);
    $wpv_wpurl = get_bloginfo('wpurl');
    $wpv_replace_wpurl = '/^http:\/\/([^\/]+).*/i';
    $wpv_replace = '/^http:\/\/(www\.)?.+\.\w+\//i';
    $wpv_permalink = preg_replace($wpv_replace, "/", $varnish_url);
    $wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, $wpv_timeout);
      if (!$varnish_sock) {
        echo "$errstr ($errno)<br />\n";
      } else {
        $out = "PURGE $wpv_permalink HTTP/1.0\r\n";
        $out .= "Host: $wpv_host\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($varnish_sock, $out);
        fclose($varnish_sock);
      }
    }
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
    }

    $wpv_wpurl = get_bloginfo('wpurl');
    $wpv_replace_wpurl = '/^http:\/\/([^\/]+)(.*)/i';
    $wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
    $wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
    $wpv_url = $wpv_blogaddr . $wpv_url;

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, 30);
      if (!$varnish_sock) {
        echo "$errstr ($errno)<br />\n";
      } else {
        $out = "PURGE $wpv_url HTTP/1.0\r\n";
        $out .= "Host: $wpv_host\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($varnish_sock, $out);
        fclose($varnish_sock);
      }
    }
  }
}

$wpvarnish = & new WPVarnish();

?>
