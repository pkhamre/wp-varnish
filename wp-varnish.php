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

if ( !defined( 'ABSPATH' ) ) {
	exit();
}
class WPVarnish {

	/**
	 * Constructor, register hooks and init plugin
	 */
	public function __construct() {
		// Option exists ?
		$this->install();

		// Localization init
		add_action( 'init', array( $this, 'initLocalization' ) );
		
		// Check user purge
		add_action( 'init', array( $this, 'checkUserPurgeActions' ) );

		// Add Administration Interface
		add_action( 'admin_menu', array( $this, 'AdminMenu' ) );

		// Add Purge Links to Admin Bar
		add_action( 'admin_bar_menu', array( $this, 'AdminBarLinks' ), 100 );

		// When posts/pages are published, edited or deleted
		// 'edit_post' is not used as it is also executed when a comment is changed,
		// causing the plugin to purge several URLs (WPVarnishPurgeCommonObjects)
		// that do not need purging.
		// When a post or custom post type is published, or if it is edited and its status is "published".
		add_action( 'publish_post', array( $this, 'PurgePost' ), 99 );
		add_action( 'publish_post', array( $this, 'PurgeCommonObjects' ), 99 );
		// When a page is published, or if it is edited and its status is "published".
		add_action( 'publish_page', array( $this, 'PurgePost' ), 99 );
		add_action( 'publish_page', array( $this, 'PurgeCommonObjects' ), 99 );
		// When an attachment is updated.
		add_action( 'edit_attachment', array( $this, 'PurgePost' ), 99 );
		add_action( 'edit_attachment', array( $this, 'PurgeCommonObjects' ), 99 );
		// Runs just after a post is added via email.
		add_action( 'publish_phone', array( $this, 'PurgePost' ), 99 );
		add_action( 'publish_phone', array( $this, 'PurgeCommonObjects' ), 99 );
		// Runs when a post is published via XMLRPC request, or if it is edited via XMLRPC and its status is "published".
		add_action( 'xmlrpc_publish_post', array( $this, 'PurgePost' ), 99 );
		add_action( 'xmlrpc_publish_post', array( $this, 'PurgeCommonObjects' ), 99 );
		// Runs when a future post or page is published.
		add_action( 'publish_future_post', array( $this, 'PurgePost' ), 99 );
		add_action( 'publish_future_post', array( $this, 'PurgeCommonObjects' ), 99 );
		// When post status is changed
		add_action( 'transition_post_status', array( $this, 'PurgePostStatus' ), 99, 3 );
		add_action( 'transition_post_status', array( $this, 'PurgeCommonObjectsStatus' ), 99, 3 );
		// When posts, pages, attachments are deleted
		add_action( 'deleted_post', array( $this, 'PurgePost' ), 99 );
		add_action( 'deleted_post', array( $this, 'PurgeCommonObjects' ), 99 );

		// When comments are made, edited or deleted
		// See: http://codex.wordpress.org/Plugin_API/Action_Reference#Comment.2C_Ping.2C_and_Trackback_Actions
		add_action( 'comment_post', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'edit_comment', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'deleted_comment', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'trashed_comment', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'pingback_post', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'trackback_post', array( $this, 'PurgePostComments' ), 99 );
		add_action( 'wp_set_comment_status', array( $this, 'PurgePostCommentsStatus' ), 99 );

		// When Theme is changed, Thanks dupuis
		add_action( 'switch_theme', array( $this, 'PurgeAll' ), 99 );

		// When a new plugin is loaded
		// this was added due to Issue #12, but, doesn't do what was intended
		// commenting this out gets rid of the incessant purging.
		//add_action('plugins_loaded',array($this, 'PurgeAll'), 99);
	}

	/**
	 * Always execute install and add_option if need
	 */
	public function install() {
		if ( (get_option( "wpvarnish_addr" ) == FALSE ) ) {
			add_option( "wpvarnish_addr", array( "127.0.0.1" ), '', 'yes' );
		}

		if ( (get_option( "wpvarnish_port" ) == FALSE ) ) {
			add_option( "wpvarnish_port", array( 80 ), '', 'yes' );
		}

		if ( (get_option( "wpvarnish_secret" ) == FALSE ) ) {
			add_option( "wpvarnish_secret", array( "" ), '', 'yes' );
		}

		if ( (get_option( "wpvarnish_timeout" ) == FALSE ) ) {
			add_option( "wpvarnish_timeout", 5, '', 'yes' );
		}

		if ( (get_option( "wpvarnish_update_pagenavi" ) == FALSE ) ) {
			add_option( "wpvarnish_update_pagenavi", 0, '', 'yes' );
		}

		if ( (get_option( "wpvarnish_update_commentnavi" ) == FALSE ) ) {
			add_option( "wpvarnish_update_commentnavi", 0, '', 'yes' );
		}

		if ( (get_option( "wpvarnish_use_adminport" ) == FALSE ) ) {
			add_option( "wpvarnish_use_adminport", 0, '', 'yes' );
		}

		if ( (get_option( "wpvarnish_vversion" ) == FALSE ) ) {
			add_option( "wpvarnish_vversion", 2, '', 'yes' );
		}
		
		if ( (get_option( "wpvarnish_purge_debug" ) == FALSE ) ) {
			add_option( "wpvarnish_purge_debug", 0, '', 'yes' );
		}
	}

	/**
	 * Load plugin translation file
	 */
	public function initLocalization() {
		load_plugin_textdomain( 'wp-varnish', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	/**
	 * Check user action for purge blog or post
	 */
	public function checkUserPurgeActions() {
		if ( isset($_GET['wpvarnish_action']) ) {
			check_admin_referer('wp-varnish');

			if ( $_GET['wpvarnish_action'] == 'clear_blog' ) {
				$this->PurgeAll();
			} elseif ( $_GET['wpvarnish_action'] == 'clear_post' && isset($_GET['wpvarnish_post_id']) ) {
				$this->PurgePost( (int) $_GET['wpvarnish_post_id'] );
			}
			
			$destination = wp_get_referer();
			if ( $destination == false ) {
				$destination = remove_query_arg('wpvarnish_action');
				$destination = remove_query_arg('wpvarnish_post_id', $destination);
				$destination = remove_query_arg('_wpnonce', $destination);
			}
			
			wp_redirect( $destination );
			exit();
		}
	}

	/**
	 * Clear all blog cache, using a regex. Use carefully !
	 */
	public function PurgeAll() {
		$this->PurgeObject( '/.*' );
	}

	/**
	 * Clear cache using a URL, clear the cache
	 * 
	 * @param string $wpv_purl
	 */
	public function PurgeURL( $wpv_purl ) {
		$wpv_purl = preg_replace( '#^https?://[^/]+#i', '', $wpv_purl );
		$this->PurgeObject( $wpv_purl );
	}

	/**
	 * wrapper on PurgeCommonObjects method for transition_post_status hook
	 * 
	 * @param string $old
	 * @param string $new
	 * @param WP_Post $post
	 */
	public function PurgeCommonObjectsStatus( $old, $new, $post ) {
		if ( $old != $new ) {
			if ( $old == 'publish' || $new == 'publish' ) {
				$this->PurgeCommonObjects( $post->ID );
			}
		}
	}

	/**
	 * Purge related objects
	 * 
	 * @param integer $post_id
	 * @return boolean
	 */
	public function PurgeCommonObjects( $post_id ) {

		$post = get_post( $post_id );
		// We need a post object in order to generate the archive URLs which are
		// related to the post. We perform a few checks to make sure we have a
		// post object.
		if ( !is_object( $post ) || !isset( $post->post_type ) || !in_array( get_post_type( $post ), array( 'post' ) ) ) {
			// Do nothing for pages, attachments.
			return false;
		}

		// NOTE: Policy for archive purging
		// By default, only the first page of the archives is purged. If
		// 'wpv_update_pagenavi_optname' is checked, then all the pages of each
		// archive are purged.
		if ( get_option( "wpvarnish_update_pagenavi" ) == 1 ) {
			// Purge all pages of the archive.
			$archive_pattern = '(?:page/[\d]+/)?$';
		} else {
			// Only first page of the archive is purged.
			$archive_pattern = '$';
		}

		// Front page (latest posts OR static front page)
		$this->PurgeObject( '/' . $archive_pattern );

		// Static Posts page (Added only if a static page used as the 'posts page')
		if ( get_option( 'show_on_front', 'posts' ) == 'page' && intval( get_option( 'page_for_posts', 0 ) ) > 0 ) {
			$posts_page_url = preg_replace( '#^https?://[^/]+#i', '', get_permalink( intval( get_option( 'page_for_posts' ) ) ) );
			$this->PurgeObject( $posts_page_url . $archive_pattern );
		}

		// Feeds
		$this->PurgeObject( '/feed/(?:(atom|rdf)/)?$' );

		// Category, Tag, Author and Date Archives
		// We get the URLs of the category and tag archives, only for
		// those categories and tags which have been attached to the post.
		// Category Archive
		$category_slugs = array();
		foreach ( get_the_category( $post->ID ) as $cat ) {
			$category_slugs[] = $cat->slug;
		}
		if ( !empty( $category_slugs ) ) {
			if ( count( $category_slugs ) > 1 ) {
				$cat_slug_pattern = '(' . implode( '|', $category_slugs ) . ')';
			} else {
				$cat_slug_pattern = implode( '', $category_slugs );
			}
			$this->PurgeObject( '/' . get_option( 'category_base', 'category' ) . '/' . $cat_slug_pattern . '/' . $archive_pattern );
		}

		// Tag Archive
		$tag_slugs = array();
		foreach ( get_the_tags( $post->ID ) as $tag ) {
			$tag_slugs[] = $tag->slug;
		}
		if ( !empty( $tag_slugs ) ) {
			if ( count( $tag_slugs ) > 1 ) {
				$tag_slug_pattern = '(' . implode( '|', $tag_slugs ) . ')';
			} else {
				$tag_slug_pattern = implode( '', $tag_slugs );
			}
			$this->PurgeObject( '/' . get_option( 'tag_base', 'tag' ) . '/' . $tag_slug_pattern . '/' . $archive_pattern );
		}

		// Author Archive
		$author_archive_url = preg_replace( '#^https?://[^/]+#i', '', get_author_posts_url( $post->post_author ) );
		$this->PurgeObject( $author_archive_url . $archive_pattern );

		// Date based archives
		$archive_year = mysql2date( 'Y', $post->post_date );
		$archive_month = mysql2date( 'm', $post->post_date );
		$archive_day = mysql2date( 'd', $post->post_date );
		// Yearly Archive
		$archive_year_url = preg_replace( '#^https?://[^/]+#i', '', get_year_link( $archive_year ) );
		$this->PurgeObject( $archive_year_url . $archive_pattern );
		// Monthly Archive
		$archive_month_url = preg_replace( '#^https?://[^/]+#i', '', get_month_link( $archive_year, $archive_month ) );
		$this->PurgeObject( $archive_month_url . $archive_pattern );
		// Daily Archive
		$archive_day_url = preg_replace( '#^https?://[^/]+#i', '', get_day_link( $archive_year, $archive_month, $archive_day ) );
		$this->PurgeObject( $archive_day_url . $archive_pattern );

		return true;
	}

	/**
	 * wrapper on PurgePost method for transition_post_status hook
	 * 
	 * @param string $old
	 * @param string $new
	 * @param WP_Post $post
	 */
	public function PurgePostStatus( $old, $new, $post ) {
		if ( $old != $new ) {
			if ( $old == 'publish' || $new == 'publish' ) {
				$this->PurgePost( $post->ID );
			}
		}
	}

	/**
	 * Purges a post object
	 * 
	 * @param integer $post_id
	 * @param boolean $purge_comments
	 * @return boolean
	 */
	public function PurgePost( $post_id, $purge_comments = false ) {

		$post = get_post( $post_id );
		// We need a post object, so we perform a few checks.
		if ( !is_object( $post ) || !isset( $post->post_type ) || !in_array( get_post_type( $post ), array( 'post', 'page', 'attachment' ) ) ) {
			return false;
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
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			$my_post = clone $post;
			$my_post->post_status = 'published';
			$my_post->post_name = sanitize_title( $my_post->post_name ? $my_post->post_name : $my_post->post_title, $my_post->ID );
			$wpv_url = get_permalink( $my_post );
		} else {
			$wpv_url = get_permalink( $post->ID );
		}

		$wpv_url = preg_replace( '#^https?://[^/]+#i', '', $wpv_url );

		// Purge post comments feed and comment pages, if requested, before
		// adding multipage support.
		if ( $purge_comments === true ) {
			// Post comments feed
			$this->PurgeObject( $wpv_url . 'feed/(?:(atom|rdf)/)?$' );
			// For paged comments
			if ( intval( get_option( 'page_comments', 0 ) ) == 1 ) {
				if ( get_option( "wpvarnish_update_commentnavi" ) == 1 ) {
					$this->PurgeObject( $wpv_url . 'comment-page-[\d]+/(?:#comments)?$' );
				}
			}
		}

		// Add support for multipage content for posts and pages
		if ( in_array( get_post_type( $post ), array( 'post', 'page' ) ) ) {
			$wpv_url .= '([\d]+/)?$';
		}
		// Purge object permalink
		$this->PurgeObject( $wpv_url );

		// For attachments, also purge the parent post, if it is published.
		if ( get_post_type( $post ) == 'attachment' ) {
			if ( $post->post_parent > 0 ) {
				$parent_post = get_post( $post->post_parent );
				if ( $parent_post->post_status == 'publish' ) {
					// If the parent post is published, then purge its permalink
					$wpv_url = preg_replace( '#^https?://[^/]+#i', '', get_permalink( $parent_post->ID ) );
					$this->PurgeObject( $wpv_url );
				}
			}
		}

		return true;
	}

	/**
	 * Wrapper on PurgePostComments for comment status changes hook
	 * 
	 * @param integer $comment_id
	 * @param string $new_comment_status
	 */
	public function PurgePostCommentsStatus( $comment_id, $new_comment_status ) {
		$this->PurgePostComments( $comment_id );
	}

	/**
	 * Purge all comments pages from a post
	 * 
	 * @param integer $comment_id
	 */
	public function PurgePostComments( $comment_id ) {
		$comment = get_comment( $comment_id );
		$post = get_post( $comment->comment_post_ID );

		// Comments feed
		$this->PurgeObject( '/comments/feed/(?:(atom|rdf)/)?$' );

		// Purge post page, post comments feed and post comments pages
		$this->PurgePost( $post, $purge_comments = true );

		// Popup comments
		// See:
		// - http://codex.wordpress.org/Function_Reference/comments_popup_link
		// - http://codex.wordpress.org/Template_Tags/comments_popup_script
		$this->PurgeObject( '/.*comments_popup=' . $post->ID . '.*' );
	}

	/**
	 * Get post id for current page
	 * 
	 * @global array $posts
	 * @global integer $comment_post_ID
	 * @global integer $post_ID
	 * @return integer
	 */
	public function getPostID() {
		global $posts, $comment_post_ID, $post_ID;

		if ( $post_ID ) {
			return (int) $post_ID;
		} elseif ( $comment_post_ID ) {
			return (int) $comment_post_ID;
		} elseif ( is_single() || is_page() && count( $posts ) ) {
			return (int) $posts[0]->ID;
		} elseif ( isset( $_REQUEST['p'] ) ) {
			return (int) $_REQUEST['p'];
		}

		return 0;
	}

	/**
	 * Add plugin settings page on menu
	 */
	public function AdminMenu() {
		if ( !defined( 'VARNISH_HIDE_ADMINMENU' ) ) {
			add_options_page( __( 'WP-Varnish Configuration', 'wp-varnish' ), 'WP-Varnish', 'manage_options', 'WPVarnish', array( $this, 'drawAdmin' ) );
		}
	}

	/**
	 * Add links for purge cache on WP admin bar
	 * 
	 * @param WP_Admin_Bar $admin_bar
	 */
	public function AdminBarLinks( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id' => 'wp-varnish',
			'title' => __( 'Varnish', 'wp-varnish' ),
			'href' => false
		) );
		$admin_bar->add_menu( array(
			'id' => 'clear-all-cache',
			'parent' => 'wp-varnish',
			'title' => 'Purge All Cache',
			'href' => wp_nonce_url( add_query_arg( array( 'wpvarnish_action' => 'clear_blog' ) ), 'wp-varnish' )
		) );
		if ( $this->getPostID() > 0 ) {
			$admin_bar->add_menu( array(
				'id' => 'clear-single-cache',
				'parent' => 'wp-varnish',
				'title' => 'Purge This Page',
				'href' => wp_nonce_url( add_query_arg( array( 'wpvarnish_action' => 'clear_post', 'wpvarnish_post_id' => $this->getPostID() ) ), 'wp-varnish' )
			) );
		}
	}

	/**
	 * Draw the administration interface.
	 * 
	 * @global array $varnish_servers
	 */
	public function drawAdmin() {
		global $varnish_servers;

		if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
			if ( current_user_can( 'manage_options' ) ) {
				if ( isset( $_POST['wpvarnish_admin'] ) ) {

					if ( !empty( $_POST["wpvarnish_addr"] ) ) {
						self::cleanSubmittedData( 'wpvarnish_addr', '/[^0-9.]/' );
						update_option( "wpvarnish_addr", $_POST["wpvarnish_addr"] );
					}

					if ( !empty( $_POST["wpvarnish_port"] ) ) {
						self::cleanSubmittedData( 'wpvarnish_port', '/[^0-9]/' );
						update_option( "wpvarnish_port", $_POST["wpvarnish_port"] );
					}

					if ( !empty( $_POST["wpvarnish_secret"] ) ) {
						update_option( "wpvarnish_secret", $_POST["wpvarnish_secret"] );
					}

					if ( !empty( $_POST["wpvarnish_timeout"] ) ) {
						update_option( "wpvarnish_timeout", (int) $_POST["wpvarnish_timeout"] );
					}

					if ( !empty( $_POST["wpvarnish_update_pagenavi"] ) ) {
						update_option( "wpvarnish_update_pagenavi", 1 );
					} else {
						update_option( "wpvarnish_update_pagenavi", 0 );
					}

					if ( !empty( $_POST["wpvarnish_update_commentnavi"] ) ) {
						update_option( "wpvarnish_update_commentnavi", 1 );
					} else {
						update_option( "wpvarnish_update_commentnavi", 0 );
					}

					if ( !empty( $_POST["wpvarnish_use_adminport"] ) ) {
						update_option( "wpvarnish_use_adminport", 1 );
					} else {
						update_option( "wpvarnish_use_adminport", 0 );
					}
					
					if ( !empty( $_POST["wpvarnish_purge_debug"] ) ) {
						update_option( "wpvarnish_purge_debug", 1 );
					} else {
						update_option( "wpvarnish_purge_debug", 0 );
					}

					if ( !empty( $_POST["wpvarnish_vversion"] ) ) {
						update_option( "wpvarnish_vversion", (int) $_POST["wpvarnish_vversion"] );
					}
				}

				if ( isset( $_POST['wpvarnish_purge_url_submit'] ) ) {
					$this->PurgeURL( $_POST["wpvarnish_purge_url"] );
				}

				if ( isset( $_POST['wpvarnish_clear_blog_cache'] ) ) {
					$this->PurgeAll();
				}
				?><div class="updated"><p><?php echo __( 'Settings Saved!', 'wp-varnish' ); ?></p></div><?php
			} else {
				?><div class="updated"><p><?php echo __( 'You do not have the privileges.', 'wp-varnish' ); ?></p></div><?php
					}
				}
				?>
		<div class="wrap">
			<script type="text/javascript" src="<?php echo plugins_url( 'wp-varnish.js', __FILE__ ); ?>"></script>
			<h2><?php echo __( "WordPress Varnish Administration", 'wp-varnish' ); ?></h2>
			<h3><?php echo __( "IP address and port configuration", 'wp-varnish' ); ?></h3>
			<form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<?php
		// Can't be edited - already defined in wp-config.php
		if ( is_array( $varnish_servers ) ) {
			echo "<p>" . __( "These values can't be edited since there's a global configuration located in <em>wp-config.php</em>. If you want to change these settings, please update the file or contact the administrator.", 'wp-varnish' ) . "</p>\n";
			// Also, if defined, show the varnish servers configured (VARNISH_SHOWCFG)
			if ( defined( 'VARNISH_SHOWCFG' ) ) {
				echo "<h3>" . __( "Current configuration:", 'wp-varnish' ) . "</h3>\n";
				echo "<ul>";
				if ( self::isHardcodedVarnishVersion() ) {
					echo "<li>" . __( "Version: ", 'wp-varnish' ) . self::getVarnishVersion() . "</li>";
				}
				foreach ( $varnish_servers as $server ) {
					@list ($host, $port, $secret) = explode( ':', $server );
					echo "<li>" . __( "Server: ", 'wp-varnish' ) . $host . "<br/>" . __( "Port: ", 'wp-varnish' ) . $port . "</li>";
				}
				echo "</ul>";
			}
		} else {
			// If not defined in wp-config.php, use individual configuration.
			?>
					 <!-- <table class="form-table" id="form-table" width=""> -->
					<table class="form-table" id="form-table">
						<tr valign="top">
							<th scope="row"><?php echo __( "Varnish Administration IP Address", 'wp-varnish' ); ?></th>
							<th scope="row"><?php echo __( "Varnish Administration Port", 'wp-varnish' ); ?></th>
							<th scope="row"><?php echo __( "Varnish Secret", 'wp-varnish' ); ?></th>
						</tr>
						<script>
			<?php
			$addrs = get_option( "wpvarnish_addr" );
			$ports = get_option( "wpvarnish_port" );
			$secrets = get_option( "wpvarnish_secret" );
			//echo "rowCount = $i\n";
			for ( $i = 0; $i < count( $addrs ); $i++ ) {
				// let's center the row creation in one spot, in javascript
				echo "addRow('form-table', $i, '$addrs[$i]', $ports[$i], '$secrets[$i]');\n";
			}
			?>
						</script>
					</table>

					<br/>

					<table>
						<tr>
							<td colspan="3"><input type="button" class="" name="wpvarnish_admin" value="+" onclick="addRow('form-table', rowCount)" /> <?php echo __( "Add one more server", 'wp-varnish' ); ?></td>
						</tr>
					</table>
			<?php
		}
		?>
				<p><?php echo __( "Timeout", 'wp-varnish' ); ?>: <input class="small-text" type="text" name="wpvarnish_timeout" value="<?php echo get_option( "wpvarnish_timeout" ); ?>" /> <?php echo __( "seconds", 'wp-varnish' ); ?></p>
				
				<p><input type="checkbox" name="wpvarnish_use_adminport" value="1" <?php checked( get_option( "wpvarnish_use_adminport" ), 1 ); ?>/> <?php echo __( "Use admin port instead of PURGE method.", 'wp-varnish' ); ?></p>

				<p><input type="checkbox" name="wpvarnish_purge_debug" value="1" <?php checked( get_option( "wpvarnish_purge_debug" ), 1 ); ?>/> <?php echo __( "Enable log purge Varnish request to a file called debug-varnish.log ", 'wp-varnish' ); ?></p>

				<p><input type="checkbox" name="wpvarnish_update_pagenavi" value="1" <?php checked( get_option( "wpvarnish_update_pagenavi" ), 1 ); ?>/> <?php echo __( "Also purge all page navigation (experimental, use carefully, it will include a bit more load on varnish servers.)", 'wp-varnish' ); ?></p>

				<p><input type="checkbox" name="wpvarnish_update_commentnavi" value="1" <?php checked( get_option( "wpvarnish_update_commentnavi" ), 1 ); ?>/> <?php echo __( "Also purge all comment navigation (experimental, use carefully, it will include a bit more load on varnish servers.)", 'wp-varnish' ); ?></p>

				<p>
		<?php echo __( 'Varnish Version', 'wp-varnish' ); ?>: 
					<?php if ( self::isHardcodedVarnishVersion() ) : ?>
						<code><?php echo self::getVarnishVersion(); ?></code>
					<?php else: ?>
						<select name="wpvarnish_vversion">
							<option value="2" <?php selected( get_option( "wpvarnish_vversion" ), 2 ); ?>/> 2 </option>
							<option value="3" <?php selected( get_option( "wpvarnish_vversion" ), 3 ); ?>/> 3 </option>
						</select>
		<?php endif; ?>
				</p>

				<p class="submit"><input type="submit" class="button-primary" name="wpvarnish_admin" value="<?php echo __( "Save Changes", 'wp-varnish' ); ?>" /></p>

				<p>
		<?php echo __( 'Purge a URL', 'wp-varnish' ); ?>:<input class="text" type="text" name="wpvarnish_purge_url" value="<?php echo self::getBaseURL( '/' ); ?>" />
					<input type="submit" class="button-primary" name="wpvarnish_purge_url_submit" value="<?php echo __( "Purge", 'wp-varnish' ); ?>" />
				</p>

				<p class="submit">
					<input type="submit" class="button-primary" name="wpvarnish_clear_blog_cache" value="<?php echo __( "Purge All Blog Cache", 'wp-varnish' ); ?>" /> 
		<?php echo __( "Use only if necessary, and carefully as this will include a bit more load on varnish servers.", 'wp-varnish' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Takes a location as an argument and purges this object from the varnish cache.
	 * 
	 * @global array $varnish_servers
	 * @param string $wpv_url
	 */
	public function PurgeObject( $wpv_url ) {
		global $varnish_servers;

		if ( is_array( $varnish_servers ) ) {
			foreach ( $varnish_servers as $server ) {
				list ($host, $port, $secret) = explode( ':', $server );
				$wpv_purgeaddr[] = $host;
				$wpv_purgeport[] = $port;
				$wpv_secret[] = $secret;
			}
		} else {
			$wpv_purgeaddr = get_option( "wpvarnish_addr" );
			$wpv_purgeport = get_option( "wpvarnish_port" );
			$wpv_secret = get_option( "wpvarnish_secret" );
		}

		$wpv_timeout = get_option( "wpvarnish_timeout" );
		$wpv_use_adminport = get_option( "wpvarnish_use_adminport" );

		$wpv_wpurl = self::getBaseURL();
		$wpv_replace_wpurl = '/^https?:\/\/([^\/]+)(.*)/i';
		$wpv_host = preg_replace( $wpv_replace_wpurl, "$1", $wpv_wpurl );
		$wpv_blogaddr = preg_replace( $wpv_replace_wpurl, "$2", $wpv_wpurl );
		$wpv_url = $wpv_blogaddr . $wpv_url;
		
		// Start debug_log
		$debug_log = sprintf( 'wp-varnish log: url = %s / host = %s on (', $wpv_url, $wpv_host);
		
		$j = 0;
		for ( $i = 0; $i < count( $wpv_purgeaddr ); $i++ ) {
			$varnish_sock = fsockopen( $wpv_purgeaddr[$i], $wpv_purgeport[$i], $errno, $errstr, $wpv_timeout );
			if ( !$varnish_sock ) {
				error_log( "wp-varnish error: $errstr ($errno) on server $wpv_purgeaddr[$i]:$wpv_purgeport[$i]" );
				continue;
			}

			if ( $wpv_use_adminport ) {
				$buf = fread( $varnish_sock, 1024 );
				if ( preg_match( '/(\w+)\s+Authentication required./', $buf, $matches ) ) {
					# get the secret
					$secret = $wpv_secret[$i];
					fwrite( $varnish_sock, "auth " . self::WPAuth( $matches[1], $secret ) . "\n" );
					$buf = fread( $varnish_sock, 1024 );
					if ( !preg_match( '/^200/', $buf ) ) {
						error_log( "wp-varnish error: authentication failed using admin port on server $wpv_purgeaddr[$i]:$wpv_purgeport[$i]" );
						fclose( $varnish_sock );
						continue;
					}
				}
				if ( self::getVarnishVersion() == 3 ) {
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
			fwrite( $varnish_sock, $out );
			fclose( $varnish_sock );
			
			$j++;
			
			// Complete debug log
			$debug_log .= "$wpv_purgeaddr[$i]:$wpv_purgeport[$i], ";
		}
		
		// Complete debug log
		if ( $j == 0 ) {
			$debug_log .= "no server)";
		} else {
			$debug_log = substr($debug_log, 0, -2).')';
		}
		
		self::_logVarnish( $debug_log );
	}

	/**
	 * Build varnish auth key
	 * 
	 * @param string $challenge
	 * @param string $secret
	 * @return string
	 */
	public static function WPAuth( $challenge, $secret ) {
		$ctx = hash_init( 'sha256' );
		hash_update( $ctx, $challenge );
		hash_update( $ctx, "\n" );
		hash_update( $ctx, $secret . "\n" );
		hash_update( $ctx, $challenge );
		hash_update( $ctx, "\n" );
		$sha256 = hash_final( $ctx );

		return $sha256;
	}

	/**
	 * Clean data submitted by user, apply a regexp
	 * 
	 * Helper functions
	 * FIXME: should do this in the admin console js, not here   
	 * normally I hate cleaning data and would rather validate before submit
	 * but, this fixes the problem in the cleanest method for now
	 * 
	 * @param string $varname
	 * @param string $regexp
	 * @return boolean
	 */
	public static function cleanSubmittedData( $varname, $regexp ) {
		if ( !isset( $_POST[$varname] ) ) {
			return false;
		}

		if ( is_array( $_POST[$varname] ) ) {
			foreach ( $_POST[$varname] as $key => $value ) {
				$_POST[$varname][$key] = preg_replace( $regexp, '', $value );
			}
		} else {
			$_POST[$varname] = preg_replace( $regexp, '', $_POST[$varname] );
		}

		return true;
	}

	public static function getVarnishVersion() {
		global $varnish_version;

		if ( isset( $varnish_version ) && in_array( $varnish_version, array( 2, 3 ) ) ) {
			return $varnish_version;
		}

		return get_option( "wpvarnish_vversion" );
	}

	/**
	 * Varnish version is defined in config file ?
	 * @global integer $varnish_version
	 * @return boolean
	 */
	public static function isHardcodedVarnishVersion() {
		global $varnish_version;

		if ( isset( $varnish_version ) && in_array( $varnish_version, array( 2, 3 ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get base URL, get domain mapping if plugin exists
	 * @param string $path
	 * @return string
	 */
	public static function getBaseURL( $path = '' ) {
		// check for domain mapping plugin by donncha
		if ( function_exists( 'domain_mapping_siteurl' ) ) {
			$base_url = domain_mapping_siteurl( 'NA' );
			$base_url = untrailingslashit( $base_url );
			$base_url .= $path;
		} else {
			$base_url = home_url( $path );
		}

		return $base_url;
	}
	
	/**
	 * Log message into debug file if option or constant allow it !
	 * @param string $message
	 * @return boolean
	 */
	public static function _logVarnish( $message = '' ) {
		if ( empty($message) ) {
			return false;
		}
		
		if( (int) get_option( "wpvarnish_purge_debug") == 1 || (defined( 'VARNISH_DEBUG' ) && constant( 'VARNISH_DEBUG' ) == true) ) {
			error_log( date('Y-m-d G:i:s') . ' - ' . $message . PHP_EOL, 3, WP_CONTENT_DIR . '/debug-varnish.log' );
			return true;
		}
		
		return false;
	}
}

add_action( 'plugins_loaded', '_init_wp_varnish' );

function _init_wp_varnish() {
	global $wpvarnish;
	$wpvarnish = new WPVarnish();
}
