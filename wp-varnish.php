<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://www.supertrendy.no/wp-varnish/
Version: 0.1
Author: <a href="http://www.supertrendy.no/">Pål-Kristian Hamre</a>
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

add_action('edit_post', 'sendVarnishPurge');

function sendVarnishPurge() {
  $varnish_url = get_permalink();
  $varnish_sock = fsockopen("localhost", 80, $errno, $errstr, 30);
  if (!$varnish_sock) {
    echo "$errstr ($errno)<br />\n";
  } else {
    $out = "PURGE $varnish_rul HTTP/1.0\r\n";
    $out .= "Host: www.example.com\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($varnish_sock, $out);
    while (!feof($varnish_sock)) {
      echo fgets($varnish_sock, 128);
    }
  
    fclose($varnish_sock);
  }
}
?>
