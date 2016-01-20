<?php
/*
Plugin Name: WordPress Varnish
Plugin URI: http://github.com/pkhamre/wp-varnish
Version: 0.8
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
  public $wpv_purgeactions;

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
    $this->wpv_purgeactions = array();
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

    // Add Purge Links to Admin Bar
    add_action('admin_bar_menu', array($this, 'WPVarnishAdminBarLinks'), 100);

    // When posts/pages are published, edited or deleted
    // 'edit_post' is not used as it is also executed when a comment is changed,
    // causing the plugin to purge several URLs (WPVarnishPurgeCommonObjects)
    // that do not need purging.
    
    // When a post or custom post type is published, or if it is edited and its status is "published".
    add_action('publish_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('publish_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // When a page is published, or if it is edited and its status is "published".
    add_action('publish_page', array($this, 'WPVarnishPurgePost'), 99);
    add_action('publish_page', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // When an attachment is updated.
    add_action('edit_attachment', array($this, 'WPVarnishPurgePost'), 99);
    add_action('edit_attachment', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // Runs just after a post is added via email.
    add_action('publish_phone', array($this, 'WPVarnishPurgePost'), 99);
    add_action('publish_phone', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // Runs when a post is published via XMLRPC request, or if it is edited via XMLRPC and its status is "published".
    add_action('xmlrpc_publish_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('xmlrpc_publish_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // Runs when a future post or page is published.
    add_action('publish_future_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('publish_future_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);
    // When post status is changed
    add_action('transition_post_status', array($this, 'WPVarnishPurgePostStatus'), 99, 3);
    add_action('transition_post_status', array($this, 'WPVarnishPurgeCommonObjectsStatus'), 99, 3);
    // When posts, pages, attachments are deleted
    add_action('deleted_post', array($this, 'WPVarnishPurgePost'), 99);
    add_action('deleted_post', array($this, 'WPVarnishPurgeCommonObjects'), 99);

    // When comments are made, edited or deleted
    // See: http://codex.wordpress.org/Plugin_API/Action_Reference#Comment.2C_Ping.2C_and_Trackback_Actions
    add_action('comment_post', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('edit_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('deleted_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('trashed_comment', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('pingback_post', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('trackback_post', array($this, 'WPVarnishPurgePostComments'),99);
    add_action('wp_set_comment_status', array($this, 'WPVarnishPurgePostCommentsStatus'),99);

    // When Theme is changed, Thanks dupuis
    add_action('switch_theme',array($this, 'WPVarnishPurgeAll'), 99);

    // When a new plugin is loaded
    // this was added due to Issue #12, but, doesn't do what was intended
    // commenting this out gets rid of the incessant purging.
    //add_action('plugins_loaded',array($this, 'WPVarnishPurgeAll'), 99);

    // Do the actual purges only on shutdown to ensure a single URL is only purged once. IOK 2016-01-20
    add_action('shutdown',array($this,'WPVarnishPurgeOnExit'),99);
  }

  function WPVarnishLocalization() {
    load_plugin_textdomain('wp-varnish', false, dirname(plugin_basename( __FILE__ ) ) . '/lang/');
  }

    // WPVarnishPurgeAll - Using a regex, clear all blog cache. Use carefully.
    function WPVarnishPurgeAll() {
        $this->WPVarnishPurgeObject('/.*');
    }

    // WPVarnishPurgeURL - Using a URL, clear the cache
    function WPVarnishPurgeURL($wpv_purl) {
        $wpv_purl = preg_replace( '#^https?://[^/]+#i', '', $wpv_purl );
        $this->WPVarnishPurgeObject($wpv_purl);
    }

    //wrapper on WPVarnishPurgeCommonObjects for transition_post_status
    function WPVarnishPurgeCommonObjectsStatus($old, $new, $post) {
        if ( $old != $new ) {
            if ( $old == 'publish' || $new == 'publish' ) {
                $this->WPVarnishPurgeCommonObjects($post->ID);
            }
        }
    }

    // Purge related objects
    function WPVarnishPurgeCommonObjects($post_id) {

        $post = get_post($post_id);
        // We need a post object in order to generate the archive URLs which are
        // related to the post. We perform a few checks to make sure we have a
        // post object.
        if ( ! is_object($post) || ! isset($post->post_type) || ! in_array( get_post_type($post), array('post') ) ) {
            // Do nothing for pages, attachments.
            return;
        }
        
        // NOTE: Policy for archive purging
        // By default, only the first page of the archives is purged. If
        // 'wpv_update_pagenavi_optname' is checked, then all the pages of each
        // archive are purged.
        if ( get_option($this->wpv_update_pagenavi_optname) == 1 ) {
            // Purge all pages of the archive.
            $archive_pattern = '(?:page/[\d]+/)?$';
        } else {
            // Only first page of the archive is purged.
            $archive_pattern = '$';
        }

        // Front page (latest posts OR static front page)
        $this->WPVarnishPurgeObject( '/' . $archive_pattern );

        // Static Posts page (Added only if a static page used as the 'posts page')
        if ( get_option('show_on_front', 'posts') == 'page' && intval(get_option('page_for_posts', 0)) > 0 ) {
            $posts_page_url = preg_replace( '#^https?://[^/]+#i', '', get_permalink(intval(get_option('page_for_posts'))) );
            $this->WPVarnishPurgeObject( $posts_page_url . $archive_pattern );
        }

        // Feeds
        $this->WPVarnishPurgeObject( '/feed/(?:(atom|rdf)/)?$' );

        // Category, Tag, Author and Date Archives

        // We get the URLs of the category and tag archives, only for
        // those categories and tags which have been attached to the post.

        // Category Archive
        $category_slugs = array();
        foreach( get_the_category($post->ID) as $cat ) {
            $category_slugs[] = $cat->slug;
        }
        if ( ! empty($category_slugs) ) {
            if ( count($category_slugs) > 1 ) {
                $cat_slug_pattern = '(' . implode('|', $category_slugs) . ')';
            } else {
                $cat_slug_pattern = implode('', $category_slugs);
            }
            $this->WPVarnishPurgeObject( '/' . get_option('category_base', 'category') . '/' . $cat_slug_pattern . '/' . $archive_pattern );
        }

        // Tag Archive
        $tag_slugs = array();
        foreach( get_the_tags($post->ID) as $tag ) {
            $tag_slugs[] = $tag->slug;
        }
        if ( ! empty($tag_slugs) ) {
            if ( count($tag_slugs) > 1 ) {
                $tag_slug_pattern = '(' . implode('|', $tag_slugs) . ')';
            } else {
                $tag_slug_pattern = implode('', $tag_slugs);
            }
            $this->WPVarnishPurgeObject( '/' . get_option('tag_base', 'tag') . '/' . $tag_slug_pattern . '/' . $archive_pattern );
        }

        // Author Archive
        $author_archive_url = preg_replace('#^https?://[^/]+#i', '', get_author_posts_url($post->post_author) );
        $this->WPVarnishPurgeObject( $author_archive_url . $archive_pattern );

        // Date based archives
        $archive_year = mysql2date('Y', $post->post_date);
        $archive_month = mysql2date('m', $post->post_date);
        $archive_day = mysql2date('d', $post->post_date);
        // Yearly Archive
        $archive_year_url = preg_replace('#^https?://[^/]+#i', '', get_year_link( $archive_year ) );
        $this->WPVarnishPurgeObject( $archive_year_url . $archive_pattern );
        // Monthly Archive
        $archive_month_url = preg_replace('#^https?://[^/]+#i', '', get_month_link( $archive_year, $archive_month ) );
        $this->WPVarnishPurgeObject( $archive_month_url . $archive_pattern );
        // Daily Archive
        $archive_day_url = preg_replace('#^https?://[^/]+#i', '', get_day_link( $archive_year, $archive_month, $archive_day ) );
        $this->WPVarnishPurgeObject( $archive_day_url . $archive_pattern );
    }

    //wrapper on WPVarnishPurgePost for transition_post_status
    function WPVarnishPurgePostStatus($old, $new, $post) {
        if ( $old != $new ) {
            if ( $old == 'publish' || $new == 'publish' ) {
                $this->WPVarnishPurgePost($post->ID);
            }
        }
    }

    // WPVarnishPurgePost - Purges a post object
    function WPVarnishPurgePost($post_id, $purge_comments=false) {

        $post = get_post($post_id);
        // We need a post object, so we perform a few checks.
        if ( ! is_object($post) || ! isset($post->post_type) || ! in_array( get_post_type($post), array('post', 'page', 'attachment') ) ) {
            return;
        }

        //$wpv_url = get_permalink($post->ID);
        // Here we do not use ``get_permalink()`` to get the post object's permalink,
        // because this function generates a permalink only for published posts.
        // So, for example, there is a problem when a post transitions from
        // status 'publish' to status 'draft', because ``get_permalink`` would
        // return a URL of the form, ``?p=123``, which does not exist in the cache.
        // For this reason, the following workaround is used:
        //   http://wordpress.stackexchange.com/a/42988/14743
        // It creates a clone of the post object and pretends it's published and
        // then it generates the permalink for it.
        if (in_array($post->post_status, array('draft', 'pending', 'auto-draft'))) {
            $my_post = clone $post;
            $my_post->post_status = 'published';
            $my_post->post_name = sanitize_title($my_post->post_name ? $my_post->post_name : $my_post->post_title, $my_post->ID);
            $wpv_url = get_permalink($my_post);
        } else {
            $wpv_url = get_permalink($post->ID);
        }

        $wpv_url = preg_replace( '#^https?://[^/]+#i', '', $wpv_url );

        // Purge post comments feed and comment pages, if requested, before
        // adding multipage support.
        if ( $purge_comments === true ) {
            // Post comments feed
            $this->WPVarnishPurgeObject( $wpv_url . 'feed/(?:(atom|rdf)/)?$' );
            // For paged comments
            if ( intval(get_option('page_comments', 0)) == 1 ) {
                if ( get_option($this->wpv_update_commentnavi_optname) == 1 ) {
                    $this->WPVarnishPurgeObject( $wpv_url . 'comment-page-[\d]+/(?:#comments)?$' );
                }
            }
        }

        // Add support for multipage content for posts and pages
        if ( in_array( get_post_type($post), array('post', 'page') ) ) {
            $wpv_url .= '([\d]+/)?$';
        }
        // Purge object permalink
        $this->WPVarnishPurgeObject($wpv_url);

        // For attachments, also purge the parent post, if it is published.
        if ( get_post_type($post) == 'attachment' ) {
            if ( $post->post_parent > 0 ) {
                $parent_post = get_post( $post->post_parent );
                if ( $parent_post->post_status == 'publish' ) {
                    // If the parent post is published, then purge its permalink
                    $wpv_url = preg_replace( '#^https?://[^/]+#i', '', get_permalink($parent_post->ID) );
                    $this->WPVarnishPurgeObject( $wpv_url );
                }
            }
        }
    }

    // wrapper on WPVarnishPurgePostComments for comment status changes
    function WPVarnishPurgePostCommentsStatus($comment_id, $new_comment_status) {
        $this->WPVarnishPurgePostComments($comment_id);
    }

    // WPVarnishPurgePostComments - Purge all comments pages from a post
    function WPVarnishPurgePostComments($comment_id) {
        $comment = get_comment($comment_id);
        $post = get_post( $comment->comment_post_ID );

        // Comments feed
        $this->WPVarnishPurgeObject( '/comments/feed/(?:(atom|rdf)/)?$' );

        // Purge post page, post comments feed and post comments pages
        $this->WPVarnishPurgePost($post, $purge_comments=true);

        // Popup comments
        // See:
        // - http://codex.wordpress.org/Function_Reference/comments_popup_link
        // - http://codex.wordpress.org/Template_Tags/comments_popup_script
        $this->WPVarnishPurgeObject( '/.*comments_popup=' . $post->ID . '.*' );

    }

function WPVarnishPostID() {
    global $posts, $comment_post_ID, $post_ID;

    if ($post_ID) {
        return $post_ID;
    } elseif ($comment_post_ID) {
        return $comment_post_ID;
    } elseif (is_single() || is_page() && count($posts)) {
        return $posts[0]->ID;
    } elseif (isset($_REQUEST['p'])) {
        return (integer) $_REQUEST['p'];
    }

    return 0;
}

  function WPVarnishAdminMenu() {
    if (!defined('VARNISH_HIDE_ADMINMENU')) {
      add_options_page(__('WP-Varnish Configuration','wp-varnish'), 'WP-Varnish', 'publish_posts', 'WPVarnish', array($this, 'WPVarnishAdmin'));
    }
  }

  function WPVarnishAdminBarLinks($admin_bar){
    $admin_bar->add_menu( array(
      'id'    => 'wp-varnish',
      'title' => __('Varnish','wp-varnish'),
      'href' => admin_url('admin.php?page=WPVarnish')
    ));
    $admin_bar->add_menu( array(
      'id'    => 'clear-all-cache',
      'parent' => 'wp-varnish',
      'title' => 'Purge All Cache',
      'href'  => wp_nonce_url(admin_url('admin.php?page=WPVarnish&amp;wpvarnish_clear_blog_cache&amp;noheader=true'), 'wp-varnish')
    ));
    $admin_bar->add_menu( array(
      'id'    => 'clear-single-cache',
      'parent' => 'wp-varnish',
      'title' => 'Purge This Page',
      'href'  => wp_nonce_url(admin_url('admin.php?page=WPVarnish&amp;wpvarnish_clear_post&amp;noheader=true&amp;post_id=' . $this->WPVarnishPostID() ), 'wp-varnish')
    ));
  }



  // WpVarnishAdmin - Draw the administration interface.
  function WPVarnishAdmin() {
    if ($_SERVER["REQUEST_METHOD"] == "GET") {
       if (current_user_can('manage_options')) {

          $nonce = $_REQUEST['_wpnonce'];

          if (isset($_GET['wpvarnish_clear_blog_cache']) && wp_verify_nonce( $nonce, 'wp-varnish' )) {
            $this->WPVarnishPurgeAll();
            header('Location: '.admin_url('admin.php?page=WPVarnish'));
          }

          if (isset($_GET['wpvarnish_clear_post']) && wp_verify_nonce( $nonce, 'wp-varnish' )) {
            $this->WPVarnishPurgePost($_GET['post_id']);
            header('Location: '.admin_url('admin.php?page=WPVarnish'));
          }
       }
    }elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
       if (current_user_can('manage_options')) {
          if (isset($_POST['wpvarnish_admin'])) {
             cleanSubmittedData('wpvarnish_port', '/[^0-9]/');
             cleanSubmittedData('wpvarnish_addr', '/[^0-9.]/');
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
          global $varnish_version;
          if (is_array($varnish_servers)) {
             echo "<p>" . __("These values can't be edited since there's a global configuration located in <em>wp-config.php</em>. If you want to change these settings, please update the file or contact the administrator.",'wp-varnish') . "</p>\n";
             // Also, if defined, show the varnish servers configured (VARNISH_SHOWCFG)
             if (defined('VARNISH_SHOWCFG')) {
                echo "<h3>" . __("Current configuration:",'wp-varnish') . "</h3>\n";
                echo "<ul>";
                if ( isset($varnish_version) && $varnish_version )
                   echo "<li>" . __("Version: ",'wp-varnish') . $varnish_version . "</li>";
                foreach ($varnish_servers as $server) {
                   @list ($host, $port, $secret) = explode(':', $server);
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
          //echo "rowCount = $i\n";
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

      <p><?php echo __('Varnish Version', 'wp-varnish'); ?>: <select name="wpvarnish_vversion"><option value="2" <?php if ($wpv_vversion_optval == 2) echo 'selected '?>/> 2 </option><option value="3" <?php if ($wpv_vversion_optval == 3) echo 'selected '?>/> 3 </option></select></p>

      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __("Save Changes",'wp-varnish'); ?>" /></p>

      <p>
        <?php echo __('Purge a URL', 'wp-varnish'); ?>:<input class="text" type="text" name="wpvarnish_purge_url" value="<?php echo get_bloginfo('url'), '/'; ?>" />
        <input type="submit" class="button-primary" name="wpvarnish_purge_url_submit" value="<?php echo __("Purge",'wp-varnish'); ?>" />
      </p>

      <p class="submit"><input type="submit" class="button-primary" name="wpvarnish_clear_blog_cache" value="<?php echo __("Purge All Blog Cache",'wp-varnish'); ?>" /> <?php echo __("Use only if necessary, and carefully as this will include a bit more load on varnish servers.",'wp-varnish'); ?></p>
      </form>
    </div>
  <?php
  }

  // WPVarnishPurgeObject - Takes a location as an argument and purges this object
  // from the varnish cache.
  // IOK 2015-12-21 changed to delay this to shutdown time.
   function WPVarnishPurgeObject($wpv_url) {
    $this->wpv_purgeactions[$wpv_url]=$wpv_url;
  }
  function WPVarnishPurgeOnExit() {
   $purgeurls = array_keys($this->wpv_purgeactions);
   foreach($purgeurls as $wpv_url) {
    $this->WPVarnishActuallyPurgeObject($wpv_url);
   }
  }
  function WPVarnishActuallyPurgeObject($wpv_url) {
    global $varnish_servers;

    // added this hook to enable other plugins do something when cache is purged
    do_action( 'WPVarnishPurgeObject', $wpv_url );

    if (is_array($varnish_servers)) {
       foreach ($varnish_servers as $server) {
          list ($host, $port, $secret) = explode(':', $server);
          $wpv_purgeaddr[] = $host;
          $wpv_purgeport[] = $port;
          $wpv_secret[] = $secret;
       }
    } else {
       $wpv_purgeaddr = get_option($this->wpv_addr_optname);
       $wpv_purgeport = get_option($this->wpv_port_optname);
       $wpv_secret = get_option($this->wpv_secret_optname);
    }

    $wpv_timeout = get_option($this->wpv_timeout_optname);
    $wpv_use_adminport = get_option($this->wpv_use_adminport_optname);
    global $varnish_version;
    if ( isset($varnish_version) && in_array($varnish_version, array(2,3)) )
       $wpv_vversion_optval = $varnish_version;
    else
       $wpv_vversion_optval = get_option($this->wpv_vversion_optname);

    // check for domain mapping plugin by donncha
    if (function_exists('domain_mapping_siteurl')) {
        $wpv_wpurl = domain_mapping_siteurl('NA');
    } else {
        $wpv_wpurl = get_bloginfo('url');
    }
    $wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
    $wpv_host = preg_replace($wpv_replace_wpurl, "$1", $wpv_wpurl);
    $wpv_blogaddr = preg_replace($wpv_replace_wpurl, "$2", $wpv_wpurl);
    $wpv_url = $wpv_blogaddr . $wpv_url;

    // allow custom purge functions and stop if they return false
    if (function_exists($this->wpv_custom_purge_obj_f)) {
        $f = $this->wpv_custom_purge_obj_f;
        if (!$f($wpv_url, $wpv_host))
            return;
    }

    for ($i = 0; $i < count ($wpv_purgeaddr); $i++) {
      $varnish_sock = fsockopen($wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, $wpv_timeout);
      if (!$varnish_sock) {
        error_log("wp-varnish error: $errstr ($errno) on server $wpv_purgeaddr[$i]:$wpv_purgeport[$i]");
        continue;
      }

      if($wpv_use_adminport) {
        $buf = fread($varnish_sock, 1024);
        if(preg_match('/(\w+)\s+Authentication required./', $buf, $matches)) {
          # get the secret
          $secret = $wpv_secret[$i];
          fwrite($varnish_sock, "auth " . $this->WPAuth($matches[1], $secret) . "\n");
	  $buf = fread($varnish_sock, 1024);
          if(!preg_match('/^200/', $buf)) {
            error_log("wp-varnish error: authentication failed using admin port on server $wpv_purgeaddr[$i]:$wpv_purgeport[$i]");
	    fclose($varnish_sock);
	    continue;
	  }
        }
        if ($wpv_vversion_optval == 3) {
            $out = "ban req.url ~ ^$wpv_url$ && req.http.host == $wpv_host\n";
          } else {
            $out = "purge req.url ~ ^$wpv_url && req.http.host == $wpv_host\n";
          }
      } else {
        $out = "BAN $wpv_url HTTP/1.0\r\n";
        $out .= "Host: $wpv_host\r\n";
        $out .= "User-Agent: WordPress-Varnish plugin\r\n";
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

// Helper functions
function cleanSubmittedData($varname, $regexp) {
// FIXME: should do this in the admin console js, not here   
// normally I hate cleaning data and would rather validate before submit
// but, this fixes the problem in the cleanest method for now
  foreach ($_POST[$varname] as $key=>$value) {
    $_POST[$varname][$key] = preg_replace($regexp,'',$value);
  }
}
?>
