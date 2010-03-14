<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://github.com/pkhamre/wp-varnish
Version: 0.1
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

  function WPVarnish() {
    global $post;

    $this->wpv_addr_optname = "wpvarnish_addr";
    $this->wpv_port_optname = "wpvarnish_port";
    $wpv_addr_optval = array ("127.0.0.1");
    $wpv_port_optval = array ("80");

    if ( (get_option($this->wpv_addr_optname) == FALSE) ) {
      add_option($this->wpv_addr_optname, $wpv_addr_optval, '', 'yes');
    }

    if ( (get_option($this->wpv_port_optname) == FALSE) ) {
      add_option($this->wpv_port_optname, $wpv_port_optval, '', 'yes');
    }

    add_action('admin_menu', array(&$this, 'WPVarnishAdminMenu'));
    add_action('edit_post', array(&$this, 'WPVarnishPurgePost'), $post->ID);
    add_action('edit_post', array(&$this, 'WPVarnishPurgeCommonObjects'));
    add_action('deleted_post', array(&$this, 'WPVarnishPurgeCommonObjects'));
    add_action('publish_post', array(&$this, 'WPVarnishPurgeCommonObjects'));
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

      update_option($this->wpv_addr_optname, $wpv_addr_optval);
      update_option($this->wpv_port_optname, $wpv_port_optval);
    }

    ?>
    <div class="wrap">
      <script type="text/javascript" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/wp-varnish/wp-varnish.js"></script>
      <h2>WordPress Varnish Administration</h2>
      <h3>IP address and port configuration</h3>
      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
       <table class="form-table" id="form-table">
        <tr valign="top">
            <th scope="row">Varnish Adm IP Address</th>
            <th scope="row">Varnish Adm Port</th>
        </tr>
        <script>
        <?php
          $addrs = get_option($this->wpv_addr_optname);
          $ports = get_option($this->wpv_port_optname);
          echo "rowCount = $i\n";
          for ($i = 0; $i < count ($addrs); $i++) {
             // let's center the row creation in one spot, in javascript
             echo "addRow('form-table', $i, '$addrs[$i]', $ports[$i]);\n";
        } ?>
        </script>
      </table>
      <input type="button" class="" name="wpvarnish_admin" value="+" onclick="addRow ('form-table', rowCount)" />
      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="Save Changes" /></p>
      </form>
    </div>
  <?php
  }

  // WPVarnishPurgePost - Takes a post id (number) as an argument and generates
  // the location path to the object that will be purged based on the permalink.
  function WPVarnishPurgePost($wpv_postid) {
    $varnish_url = get_permalink($wpv_postid);
    $wpv_purgeaddr = get_option($this->wpv_addr_optname);
    $wpv_purgeport = get_option($this->wpv_port_optname);
    $wpv_wpurl = get_bloginfo('wpurl');
    $wpv_replace_wpurl = '/^http:\/\//i';
    $wpv_replace = '/^http:\/\/(www\.)?.+\.\w+\//i';
    $wpv_permalink = preg_replace($wpv_replace, "/", $varnish_url);
    $wpv_host = preg_replace($wpv_replace_wpurl, "", $wpv_wpurl);

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, 30);
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
    $wpv_purgeaddr = get_option($this->wpv_addr_optname);
    $wpv_purgeport = get_option($this->wpv_port_optname);
    $wpv_wpurl = get_bloginfo('wpurl');
    $wpv_replace_wpurl = '/^http:\/\//i';
    $wpv_host = preg_replace($wpv_replace_wpurl, "", $wpv_wpurl);
    $varnish_sock = fsockopen($wpv_purgeaddr, $wpv_purgeport, $errno, $errstr, 30);
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

$wpvarnish = & new WPVarnish();

?>
