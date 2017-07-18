<?php
/**
 * @package Gallery_Objects
 * @version 0.4
 */
/*
Plugin Name: Gallery Objects
Plugin URI: http://galleryobjects.com/
Description: A highly flexible and configurabe image gallery, with various flash-like display options.  1) Upload you images.  2) Create an Album.  3) Assign a View. 4) Enter [galobj viewid=VIEWID] onto a page or post.
Author: Host Pond Web Hosting (Portland Oregon)
Version: 0.4
Author URI: http://www.hostpond.com/
License: GPL2
*/

$current_version = "0.4";

/*  Copyright 2010, Richard Powell  (email : richard@powell.pro)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/*
    ?? Future Implements ??
    $galleriffic = array( 'name' => 'Galleriffic', 'url' => 'http://www.twospy.com/galleriffic/example-2.html',
    $super = array( 'name' => 'Supersized', 'url' => 'http://www.buildinternet.com/project/supersized/default.php',
*/

GLOBAL $galobj_db_version, $wpdb, $go_table_name, $go_dir;

$galobj_db_version = "1.0";
$go_table_name = $wpdb->prefix . "gallery_objects";
$go_ipp = 20;						// images per page
$go_months = array( 1=>'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$go_dir = basename(dirname(__FILE__));
$go_folder = WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__));

$go_defaults = array(	  1 =>						// AD Gallery Default Settings (View Type 1)
				array (	 '[TRANS.TIME]' => '5000'
					,'[TRANS.SPEED]' => '400'
					,'[TRANS.EFFECT]' => 'slide-hori'
					,'[CONTAINER.WIDTH]' => '600'
					,'[CONTAINER.HEIGHT]' => '400'
					,'[CONTAINER.BACKGROUND]' => '000000'
					,'[TRANSPARENT.BACKGROUND]' => ''
					,'[DISPLAY.TITLE]' => 'true'
					,'[DISPLAY.DESCRIPTION]' => 'true'
					,'[DISPLAY.LINKFROM]' => 'none'
					,'[DESCRIPTION.ALIGN]' => 'bottom'
					,'[AUTO.START]' => 'true'
					,'[NAV.LOCATION]' => 'bottom'
					,'[THUMB.HEIGHT]' => ''
					,'[SCROLL.COLOR]' => 'white'
					,'[CONTROL.FONTCOLOR]' => '000000'
					,'[CONTROL.FONTSIZE]' => ''
					,'[CONTROL.FONTFAMILY]' => ''
					,'[DESCRIPTION.FONTCOLOR]' => '000000'
					,'[DESCRIPTION.FONTSIZE]' => ''
					,'[DESCRIPTION.FONTFAMILY]' => ''
					,'[DESCRIPTION.WIDTH]' => ''
					,'[DESCRIPTION.BACKGROUND]' => 'EEEEEE'
					,'[DESCRIPTION.TRANSPARENT]' => ''
					,'[IMAGEWRAPPER.WIDTH]' => '100%'
					,'[IMAGEWRAPPER.VALIGN]' => 'center'
					,'[DESCRIPTION.WRAPPER]' => 'false'
					,'[DESCRIPTION.HEIGHT]' => ''
					,'[TITLE.FONTCOLOR]' => '000000'
					,'[TITLE.FONTSIZE]' => ''
					,'[TITLE.FONTFAMILY]' => ''
				)
			, 2=>						// Gallerrific Default Settings (View Type 2)
				array (  '[NOTHING]' => 'NO' 
				)
		);

load_plugin_textdomain( 'gallery-objects', null, $go_dir . '/lang');

register_activation_hook( __FILE__, 'galobj_install');
register_uninstall_hook( __FILE__, 'galobj_uninstall');

add_action('admin_menu', 'galobj_plugin_menu');
add_action('wp_ajax_go_album_mod','_go_album_mod');
add_action('wp_ajax_go_view_object','_go_view_object');
add_action('wp_ajax_nopriv_go_view_object','_go_view_object');
add_action('wp_ajax_go_save_view_settings','_go_save_view_settings');
add_action('wp_ajax_go_check_for_updates','_go_check_for_updates');

add_filter('the_content','_go_show_object');

/*	****
	Install Gallery Objects, Database tables and default dataset
 *	****/

function galobj_install() {
	GLOBAL $wpdb, $galobj_db_version, $go_table_name;

	if ($wpdb->get_var("SHOW TABLES LIKE '$go_table_name'") != $go_table_name) {

		$sql = "CREATE TABLE " . $go_table_name . " (
			id int unsigned unique not null AUTO_INCREMENT,
			otype tinyint,
			oname varchar(32),
			value text,
			PRIMARY KEY  (`id`)
		);";

		require_once(ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta($sql);

		add_option("galobj_db_version", $galobj_db_version);

	}
}

function galobj_uninstall() {
	GLOBAL $wpdb, $galobj_db_version, $go_table_name;

	if ($wpdb->get_var("SHOW TABLES LIKE '$go_table_name'") == $go_table_name) {
		$sql = "DROP TABLE " . $go_table_name;
		require_once(ABSPATH . "wp-admin/includes/upgrade.php");
		$wpdb->query($sql);
	}

	delete_option("galobj_db_version", $galobj_db_version);
}

function galobj_plugin_menu() {
	add_submenu_page( 'upload.php', 'Gallery Objects', 'Gallery Objects', 'upload_files', 'gallery-objects.php', 'galobj_media_page');
}

function galobj_media_page() {
	GLOBAL $wpdb, $go_table_name, $go_months, $go_ipp, $current_version;

	$iid		= 0;
	$siteurl	= get_option('siteurl');
	$wpup		= wp_upload_dir();
	$go_folder	= WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__));

	if (!current_user_can('upload_files')) {
		wp_die(  __('You do not have sufficient permissions to access this page.','gallery-objects') );
	}

	$goval  = _goget('go-action',null,null);
	$gostep = _goget('go-step',0,0);
	$gotab	= _goget('go-tab',1,1);

	?>
	<link rel="stylesheet" type="text/css" href="<?php echo $go_folder ?>admin/css/style.css" media="screen" />
	<script src="<?php echo $go_folder; ?>admin/js/effects.js.php"></script>
	<script src="<?php echo $go_folder; ?>admin/js/jscolor/jscolor.js"></script>

	<div id="tabbed_box_1" class="tabbed_box">

	<h4>Gallery Objects <small></small></h4>

		<div class="tabbed_area">

	<?php
		switch ($goval) {

		    case 'delete-view':
		    case 'delete-album':

			if ($goval=="delete-view")	$oid = _goget('go-view-id');
			else				$oid = _goget('go-album-id');

			if ($oid) {
				$result = $wpdb->query("DELETE FROM $go_table_name WHERE id=$oid");
			} else $result = null;

			?>
			<div id="content_1" class="content">
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=1"><strong><?php _e('ALBUMS','gallery-objects'); ?></strong></a> |
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=2"><strong><?php _e('VIEWS','gallery-objects'); ?></strong></a> |
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=3"><strong><?php _e('HELP, SUPPORT, & UPDATES','gallery-objects'); ?></strong></a>
				<br /><br />
				<div class="go-form">
				<?php	if ($result) _e('Gallery Object Deleted Successfully','gallery-objects');
					else echo "<font color=red><strong>" . __('FAILED','gallery-objects') . "</strong></font> to delete Gallery Object";
				?>
				</div>
			</div>
			<?php

			if ($goval=='delete-album') {
				if (_go_album_has_view($oid)) {
					?>
					<div class="go-warning">
					<?php _e('This Album has Views Defined.  You should either delete theses views, or define new albums for them.','gallery-objects'); ?>
					</div>
					<?php
				}
			}

			break;

		    case 'new-album':

			$go_name = _goget('go-name');
			$go_desc = _goget('go-description');
			$go_imgs = array();
			$go_data = array( 'images' => $go_imgs, 'description' => $go_desc );
			$rows_effected = $wpdb->insert( $go_table_name, array( 'otype' => 1, 'oname' => $go_name,
				'value' => serialize($go_data) ) );
			echo "NEW ALBUM<br />\n";
			$iid = $wpdb->insert_id;

		    case 'edit-album';

			if (!$iid) $iid = _goget('go-album-id');
			if ( (!isset($go_name)) || (!isset($go_desc)) ) {
				$go_name = _goget('go-name',null,null);
				$go_desc = _goget('go-description',null,null);

				$golink  = $wpdb->get_row("SELECT * from $go_table_name WHERE id=$iid");
				$go_data = unserialize($golink->value);

				if (($goval=="edit-album") && ($gostep)) {
					$go_data['description'] = $go_desc;
					$rows_effected = $wpdb->update($go_table_name,array('oname'=>$go_name,'value'=>serialize($go_data)),array('id'=>$iid) );
				}

				if (!isset($go_name)) $go_name = $golink->oname;
				if (!isset($go_desc)) $go_desc = $go_data['description'];
			}

			// Print Critical RED help messages here.
			// Print Warning Yellow help messages here.

			if ( ("new-album"==$goval) || ("edit-album"==$goval) ) {
				if (!_go_album_has_view($iid)) {
					?>
					<div class="go-warning">
					<?php _e('This album currently has no view defined!','gallery-objects'); ?>
					</div>
					<br /><br />
					<?php
				}
			}


			?>
			<div id="content_1" class="content">

				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=1"><strong><?php _e('ALBUMS','gallery-objects'); ?></strong></a> | 
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=2"><strong><?php _e('VIEWS','gallery-objects'); ?></strong></a> | 
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=3"><strong><?php _e('HELP, SUPPORT, & UPDATES','gallery-objects'); ?></strong></a>
				<br /><br />
				<div class="go-form">
					<?php _e('Edit Album Settings Here.','gallery-objects'); ?>
					<form method="post">
						<input type=hidden name="go-action" value="edit-album">
						<input type=hidden name="go-step" value="1">
						<table border=0 cellpadding=4 cellspacing=4><tr><td valign=top>
						<?php _e('Album ID','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<?php echo $iid; ?></td></tr><tr><td valign=top>
						<?php _e('Album Name','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<input type=hidden name=go-album-id value="<?php echo $iid; ?>">
						<input type=text name="go-name" size=53 maxlength=64 value="<?php echo $go_name; ?>">
						</td></tr><tr><td valign=top>
						<?php _e('Album Description','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<textarea name="go-description" rows=3 cols=45><?php echo $go_desc?></textarea>
						</td></tr>
						</table>
						<input type=submit value="<?php _e('Update Album','gallery-objects'); ?>">
					</form>
				</div>
			<?php

			echo "<br /><strong><?php _e('Add or Remove Photos to/from your album below.','gallery-objects'); ?></strong><br /><br />";

			$filter_months = $wpdb->get_results("SELECT DISTINCT DATE_FORMAT(post_date,'%M %Y') as Month "
					. "FROM $wpdb->posts WHERE post_mime_type LIKE 'image/%'");
			$filter_date = _goget('go-filter-month');
			$filter_text = _goget('go-filter-text');
			$filter_paged = _goget('go-filter-paged',0,0);
			if ($filter_date!="") list ($filter_month, $filter_year) = split(" ",$filter_date);
			else { $filter_month = ""; $filter_year = ""; }
			?>

			<table border=0 cellpadding=2 cellspacing=2 width=100%><tr><td valign=top>
			<form method="post">
			<input type=hidden name="go-action" value="edit-album">
			<input type=hidden name="go-album-id" value="<?php echo $iid; ?>">
			<?php _e('Filter Images : By month','gallery-objects'); ?>
			<select name="go-filter-month">
				<option value=""><?php _e('All Months','gallery-objects'); ?></option>
				<?php
				foreach ($filter_months as $month) {
					echo "\t\t\t\t<option value=\"$month->Month\"";
					if ($month->Month==$filter_date) echo " selected";
					echo ">$month->Month</option>\n";
				}
				?>
			</select>
			<?php _e('By text','gallery-objects'); ?>
			<input type=text name="go-filter-text" size=30 maxlength=40 value="<?php echo $filter_text; ?>">
			<input type=submit value="Apply Filter">
			</form>

			</td><td align=right valign=top>
			<nobr>
			<?php
				// SQL Only for the count
				$sqlc = "SELECT COUNT(*) AS count FROM $wpdb->posts a, $wpdb->postmeta b WHERE a.ID=b.post_id AND "
					. "b.meta_key='_wp_attachment_metadata' AND (a.post_mime_type like 'image/%')";
				if ($filter_month!="") $sqlc .= " AND YEAR(post_date)=$filter_year AND MONTH(post_date)=" . array_search($filter_month,$go_months);
				if ($filter_text!="") {
					$sqlc	.= " AND (post_title like '%" . $filter_text . "%'"
					.  "     OR post_content like '%" . $filter_text . "%'"
					.  "     OR post_excerpt like '%" . $filter_text . "%')";
				}

				$go_row = $wpdb->get_row($sqlc);
				if (!$go_row) $count = 0; else $count = $go_row->count;

				// Display the Page Links

				if ( ($filter_paged + $go_ipp) > $count) $last = $count;
				else $last = $filter_paged + $go_ipp;

				_e('Displaying','gallery-objects');
				echo " " . ($filter_paged+1) . " " . __('to','gallery-objects') . " " . $last . " " . __('of','gallery-objects') . " " . $count;

				echo "</td><td valign=top>\n";

				if ($filter_paged>0) {
					$link_text = '<';
					if (($filter_paged-$go_ipp)<1) $start_id = 0; else $start_id = ($filter_paged-$go_ipp);
//					_go_show_image_nav($link_text,$start_id,$iid,$filter_month,$filter_text);
					_go_show_image_nav($link_text,$start_id,$iid,$filter_date,$filter_text);
				}

				echo "</td><td valign=top>";

				if ($count>($filter_paged+$go_ipp)) {	// More to display
					$link_text = '>';
					$start_id = $filter_paged+$go_ipp;
//					_go_show_image_nav($link_text,$start_id,$iid,$filter_month,$filter_text);
					_go_show_image_nav($link_text,$start_id,$iid,$filter_date,$filter_text);
				}

			?>
			</nobr>
			</td></tr></table>

			<?php

			// Full dump here.

			$sql = "SELECT a.ID, a.post_name, a.post_title, DATE_FORMAT(a.post_date,'%M %d, %Y') as post_date, a.post_mime_type, "
				. "a.post_content, a.guid, b.meta_value FROM $wpdb->posts a, $wpdb->postmeta b WHERE a.ID=b.post_id AND "
				. "b.meta_key='_wp_attachment_metadata' AND (a.post_mime_type like 'image/%')";

			if ($filter_month!="") $sql .= " AND YEAR(post_date)=$filter_year AND MONTH(post_date)=" . array_search($filter_month,$go_months);
			if ($filter_text!="") {
				$sql	.= " AND (post_title like '%" . $filter_text . "%'"
					.  "     OR post_content like '%" . $filter_text . "%'"
					.  "     OR post_excerpt like '%" . $filter_text . "%')";
			}

			// $go_ipp (Images Per Page)
			// $filter_paged
			// $count (is the number of results)

			$sql .= " LIMIT " . $filter_paged . "," . $go_ipp;

			$pics = $wpdb->get_results($sql);

			$eo = "odd";
			$cnt = 0;
			foreach ($pics as $tpic) {
				$cnt++;
				echo '<div class="go-list-' . $eo . '"' . ">\n";
				echo "<table border=0 cellpadding=2 cellspacing=2><tr><td valign=center width=60>\n";
				$tarray = unserialize($tpic->meta_value);
				$folder	= _gofolder($tarray['file']);
				if (isset($tarray['sizes']['thumbnail']['file'])) {
					echo '<img width="50" src="' . $wpup['baseurl'] . "/" . $folder . "/" . $tarray['sizes']['thumbnail']['file'] . '">';
				} else {
					echo '<img width="50" src="' . $wpup['baseurl'] . "/" . $tarray['file'] . '">';
				}
				echo "</td><td valign=center width=100%>\n";
				if ($tpic->post_title!="")	echo "&nbsp;&nbsp;&nbsp;<strong>" . $tpic->post_title . "</strong><br />\n";
				if ($tpic->post_content!="")	echo "&nbsp;&nbsp;&nbsp;" . $tpic->post_content . "<br />";
				echo "&nbsp;&nbsp;&nbsp;" . $tpic->post_date;
				echo "</td><td valign=center width=250>";
				echo "<input id=go-button-" . $cnt . " type=image onClick=\"go_swap_image(" . $cnt . "," . $tpic->ID . "," . $iid . ",";

				if (_go_pic_is_in_album($tpic->ID,$iid)) {
					echo "'remove')\" src=\"" . $go_folder . "images/remove.png";
				} else {
					echo "'add')\" src=\"" . $go_folder . "images/add.png";
				}
				echo "\">";
				echo "</td></tr></table>\n";
				echo "</div>\n";
				if ($eo=="odd") $eo="even"; else $eo="odd";
			}

			echo "\t\t\t</div><!-- content_1 -->\n";	// End for NEW & EDIT Album
			echo "\t\t</div><!-- tabbed_area -->\n";

			break;

		    case 'new-view':

			$go_name = _goget('go-name');
			$go_desc = _goget('go-description');
			$go_type = _goget('go-type');
			$go_aid  = _goget('go-album');

			$go_data = array( 'viewtype' => $go_type
				, 'description' => $go_desc
				, 'settings' => _goget_view_settings(0,$go_type,$go_type)
				);

			$rows_affected = $wpdb->insert( $go_table_name, array( 'otype' => 2, 'oname' => $go_aid . "^" . $go_name,
				'value' => serialize($go_data) ) );
			$iid = $wpdb->insert_id;

		    case 'edit-view':

			if (!$iid) $iid = _goget('go-view-id');
			if ( (!isset($go_name)) || (!isset($go_desc)) ) {
				$go_name = _goget('go-name',null,null);
				$go_type = _goget('go-type',1,1);
				$go_desc = _goget('go-description',null,null);
				$go_aid  = _goget('go-album');

				$golink  = $wpdb->get_row("SELECT * FROM $go_table_name WHERE id=$iid");
				$go_data = unserialize($golink->value);

				$go_data['settings'] = _goget_view_settings($iid,$go_type,$go_type);

				if ( ($goval=="edit-view") && ($gostep) ) {
					$go_data['description'] = $go_desc;
					$rows_effected = $wpdb->update($go_table_name,array('oname'=>$go_aid . "^" . $go_name,'value'=>serialize($go_data)),array('id'=>$iid) );
				}
				if (!isset($go_name)) {
					list ($go_aid,$go_name) = split("\^",$golink->oname);
				}
				if (!isset($go_desc)) $go_desc = $go_data['description'];
			}

			// Print critical RED or warn YELLOW messages here.

			?>
			<div id="content_1" class="content">
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=1"><strong><?php _e('ALBUMS','gallery-objects'); ?></strong></a> | 
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=2"><strong><?php _e('VIEWS','gallery-objects'); ?></strong></a> | 
				<a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/upload.php?page=gallery-objects.php&go-tab=3"><strong><?php _e('HELP, SUPPORT, & UPDATES','gallery-objects'); ?></strong></a>
				<br /><br />

				<table border=0 cellpadding=2 cellspacing=2><tr><td valign=top>
				<?php _e('Insert the following code into a Page or Post to display this Gallery Object View','gallery-objects'); ?>
				</td></tr><tr><td valign=top background="#333333">
				<strong>[galobj viewid=<?php echo $iid; ?>]</strong>
				</td></tr></table>
				<br /><br />

				<div class="go-form">
					<?php _e('Edit View Settings Here.','gallery-objects'); ?>
					<form method="post">
						<input type=hidden name="go-action" value="edit-view">
						<input type=hidden name="go-step" value="1">
						<input type=hidden name="go-view-id" value="<?php echo $iid; ?>">
						<table border=0 cellpadding=4 cellspacing=4><tr><td valign=top>
						<?php _e('View ID','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<?php echo $iid; ?></td></tr><tr><td valign=top>
						<?php _e('View Name','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<input type=text name="go-name" size=53 maxlength=64 value="<?php echo $go_name ?>">
						</td></tr><tr><td valign=top>
						<?php _e('View Type','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<select name="go-type">
							<option value="1">1 AD Gallery - Slideshow</option>
						</select>
						</td></tr><tr><td valign=top>
						<?php _e('Albums for this View','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<select name="go-album">
						<?php
							$albums = $wpdb->get_results("SELECT id, oname, value FROM $go_table_name WHERE otype=1");
							foreach ($albums as $album) {
								echo "<option value=\"" . $album->id . "\"";
								if ($album->id == $go_aid) echo " selected";
								echo ">" . $album->id . " - " . $album->oname . "</option>\n";
							}
						?>
						</select>
						</td></tr><tr><td valign=top>
						<?php _e('View Description','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
						<textarea name="go-description" rows=3 cols=45><?php echo $go_desc?></textarea>
						</td></tr></table>
						<input type=submit value="<?php _e('Update View','gallery-objects'); ?>">
					</form>
				</div>

			</div><!-- content_1 -->

		</div><!-- tabbed_area -->

			<?php

			echo "<br /><strong>";
			_e('Appearance Settings for this View','gallery-objects');
			echo "</strong><br /><br />\n";

			echo "<form>\n";
			echo "<input type=hidden name='viewid' value=\"" . $iid . "\">\n";
			echo "<table border=0 cellpadding=4 cellspacing=4><tr><td align=right>\n";
			_e('Container Height','gallery-objects');
			echo " </td><td align=left> : <input type=text size=6 maxlength=10 name='containerheight' value='"
				. $go_data['settings']['[CONTAINER.HEIGHT]'] . "'></td><td width=10></td><td align=right>\n";
			_e('Container Width','gallery-objects');
			echo " </td><td align=left> : <input type=text size=6 maxlength=10 name='containerwidth' value='"
				. $go_data['settings']['[CONTAINER.WIDTH]'] . "'></td></tr><tr><td align=right>\n";
			_e('Container Background','gallery-objects');
			echo " </td><td align=left> : <input class=color id=\"containerbackground\" "
				. "type=text size=8 maxlength=8 name='containerbackground' value='"
				. $go_data['settings']['[CONTAINER.BACKGROUND]'] . "'></td><td width=10></td><td align=right>\n";
			_e('Thumbnail Height','gallery-objects');
			echo " </td><td align=left> : <input type=text size=3 maxlength=3 name='thumbheight' value='"
				. $go_data['settings']['[THUMB.HEIGHT]'] . "'> " . __('eg... 25','gallery-objects')
				. "</td></tr><tr><td align=right>\n";

			_e('Transparent Background','gallery-objects');
			echo " </td><td align=left> : <input type=checkbox name='transparentbackground' value='true'";
			if ($go_data['settings']['[TRANSPARENT.BACKGROUND]'] == 'true') echo " checked";
			echo "> ";
			_e('Overrides Above Color','gallery-objects');
			echo "</td><td valign=top></td></tr><tr><td align=right>\n";

			_e('Transition Time','gallery-objects');
			echo " </td><td align=left> : <input type=text size=3 maxlenth=3 name='transtime' value='"
				. ($go_data['settings']['[TRANS.TIME]']/1000) . "'> " . __('in seconds','gallery-objects')
				. "</td><td width=100></td><td align=right>";
			_e('Transition Speed','gallery-objects');
			echo " </td><td align=left> : <input type=text size=3 maxlength=3 name='transspeed' value='"
				. ($go_data['settings']['[TRANS.SPEED]']/1000) . "'> " . __('in seconds','gallery-objects')
				. "</td></tr><tr><td align=right>";

			_e('Transition Effect','gallery-objects');
			echo " </td><td align=left> : ";
			echo "<select name='transeffect'>\n";

			echo "  <option value='slide-hori'";
			if ($go_data['settings']['[TRANS.EFFECT]']=='slide-hori') echo " selected";
			echo ">";
			_e('Slide Horizontal','gallery-objects');
			echo "</option>\n";

			echo "  <option value='slide-vert'";
			if ($go_data['settings']['[TRANS.EFFECT]']=='slide-vert') echo " selected";
			echo ">";
			_e('Slide Vertical','gallery-objects');
			echo "</option>\n";

			echo "  <option value='resize'";
			if ($go_data['settings']['[TRANS.EFFECT]']=='resize') echo " selected";
			echo ">";
			_e('Shrink/Grow','gallery-objects');
			echo "</option>\n";

			echo "  <option value='fade'";
			if ($go_data['settings']['[TRANS.EFFECT]']=='fade') echo " selected";
			echo ">";
			_e('Fade','gallery-objects');
			echo "</option>\n";

			echo "  <option value='none'";
			if ($go_data['settings']['[TRANS.EFFECT]']=='none') echo " selected";
			echo ">";
			_e('None','gallery-objects');
			echo "</option>\n";

			echo "</select></td><td width=10></td><td align=right>\n";

			_e('Scroll Arrow Color','gallery-objects');
			echo " </td><td align=left> : <input type=radio name='scrollcolor' value='white'";
			if ($go_data['settings']['[SCROLL.COLOR]']=="white") echo " checked";
			echo "> ";
			_e('White','gallery-objects');
			echo " &nbsp;&nbsp;";
			echo "<input type=radio name='scrollcolor' value='black'";
			if ($go_data['settings']['[SCROLL.COLOR]']=="black") echo " checked";
			echo "> ";
			_e('black','gallery-objects');
			echo "</td></tr><tr><td align=right>";

			_e('Display Title','gallery-objects');
			echo " </td><td align=left> : <input type=checkbox name=\"displaytitle\" value='true'";
			if ($go_data['settings']['[DISPLAY.TITLE]'] == 'true') echo " checked";
			echo "></td><td width=10></td><td align=right valign=top>\n";

			_e('Description Alignment','gallery-objects');
			echo " </td><td align=left> : ";
			echo "<select name='descriptionalign'>\n";

			echo "  <option value='top'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='top') echo " selected";
			echo ">";
			_e('Top','gallery-objects');
			echo "</option>\n";

			echo "  <option value='bottom'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='bottom') echo " selected";
			echo ">";
			_e('Bottom','gallery-objects');
			echo "</option>\n";

			echo "  <option value='outsideleft'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='outsideleft') echo " selected";
			echo ">";
			_e('Outside Left','gallery-objects');
			echo "</option>\n";

			echo "  <option value='outsideright'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='outsideright') echo " selected";
			echo ">";
			_e('Outside Right','gallery-objects');
			echo "</option>\n";

			echo "  <option value='outsidetop'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='outsidetop') echo " selected";
			echo ">";
			_e('Outside Top','gallery-objects');
			echo "</option>\n";

			echo "  <option value='outsidebottom'";
			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='outsidebottom') echo " selected";
			echo ">";
			_e('Outside Bottom','gallery-objects');
			echo "</option>\n";

//			echo "<input type=radio name='descriptionalign' value='bottom'";
//			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='bottom') echo " checked";
//			echo "> Bottom &nbsp;&nbsp;";
//			echo "<input type=radio name='descriptionalign' value='top'";
//			if ($go_data['settings']['[DESCRIPTION.ALIGN]']=='top') echo " checked";
//			echo "> ";
//			_e('Top','gallery-objects');

			echo "</select>";

			echo "\n</td></tr><tr><td align=right>\n";

			_e('Display Description','gallery-objects');
			echo " </td><td align=left> : <input type=checkbox name=\"displaydescription\""
				. " value=\"true\"";
			if ($go_data['settings']['[DISPLAY.DESCRIPTION]'] == 'true') echo " checked";
			echo "></td><td width=10></td><td align=right>\n";

			echo __('Display HREF Link From','gallery-objects') . " </td><td align=left> : ";
			echo "<input type=radio name='displaylinkfrom' value='none'";
			if ($go_data['settings']['[DISPLAY.LINKFROM]']=='none') echo " checked";
			echo "> " . __('None','gallery-objects');
			echo " &nbsp;&nbsp;<input type=radio name='displaylinkfrom' value='alt'";
			if ($go_data['settings']['[DISPLAY.LINKFROM]']=='alt') echo " checked";
			echo "> " . __('Alt','gallery-objects') . " &nbsp;&nbsp;";
			echo "<input type=radio name='displaylinkfrom' value='caption'";
			if ($go_data['settings']['[DISPLAY.LINKFROM]']=='caption') echo " checked";
			echo "> " . __('Caption','gallery-objects') . "\n";
			echo "</td></tr><tr><td align=right>\n";

			echo __('Auto Start Slideshow','gallery-objects') . " </td><td align=left> : <input type=checkbox name='autostart' value='true'";
			if ($go_data['settings']['[AUTO.START]']=='true') echo " checked";
			echo ">";
			echo "</td><td width=10></td><td align=right>\n";

			echo __('Display thumbnails on','gallery-objects') . " </td><td align=left> : ";

			echo "<input type=radio name='navlocation' value='top'";
			if ($go_data['settings']['[NAV.LOCATION]']=='top') echo " checked";
			echo "> " . __('Top','gallery-objects') . " &nbsp;&nbsp;";

			echo "<input type=radio name='navlocation' value='bottom'";
			if ($go_data['settings']['[NAV.LOCATION]']=='bottom') echo " checked";
			echo "> " . __('Bottom','gallery-objects') . " &nbsp;&nbsp;";

			echo "<input type=radio name='navlocation' value='none'";
			if ($go_data['settings']['[NAV.LOCATION]']=='none') echo " checked";
			echo "> " . __('Not at all','gallery-objects');
			echo "</td></tr>\n";

			echo "<tr><td align=center colspan=5>\n";
			echo "<button onClick=\"go_sh(); return false;\">";
			_e('SHOW / HIDE Advanced Settings','gallery-objects');
			echo "</button>";
			echo "</td></tr>\n";

			echo "<tr id='go-as-1' style='display: none;'><td valign=top align=right>";
			_e('Control Font Color','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=8 maxlength=8 class=color name='controlfontcolor' value='"
				. $go_data['settings']['[CONTROL.FONTCOLOR]'] . "'>\n";
			echo "</td><td width=10></td><td valign=top align=right>";
			_e('Control Font Size','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=4 maxlength=12 name='controlfontsize' value='"
				. $go_data['settings']['[CONTROL.FONTSIZE]'] . "'> " . __('eg. 0.8em','gallery-objects') . "\n";
			echo "</td></tr>\n";

			echo "<tr id='go-as-2' style='display: none;'><td valign=top align=right>";
			_e('Control Font Family','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=10 maxlength=255 name='controlfontfamily' value='"
				. stripslashes(stripslashes($go_data['settings']['[CONTROL.FONTFAMILY]']))
				. "'> " . __('Arial, Verdana','gallery-objects') . "\n";
			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Description Font Color','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=8 maxlength=8 class=color name='descriptionfontcolor' value='"
				. $go_data['settings']['[DESCRIPTION.FONTCOLOR]'] . "'>\n";
			echo "</td></tr>\n";

			echo "<tr id='go-as-3' style='display: none;'><td valign=top align=right>";
			_e('Description Font Size','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=4 maxlength=12 name='descriptionfontsize' value=\""
				. $go_data['settings']['[DESCRIPTION.FONTSIZE]'] . "\">" . __('eg. 0.8em','gallery-objects') . "\n";
			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Description Font Family','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=10 maxlength=255 name='descriptionfontfamily' value='"
				. stripslashes(stripslashes($go_data['settings']['[DESCRIPTION.FONTFAMILY]']))
				. "'>" . __('Arial, Verdana','gallery-objects') . "\n";
			echo "</td></tr>\n";

			echo "<tr id='go-as-4' style='display: none;'><td valign=top align=right>";
			_e('Title Font Color','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=8 maxlength=8 class=color name='titlefontcolor' value='"
				. $go_data['settings']['[TITLE.FONTCOLOR]'] . "'>\n";
			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Title Font Size','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=4 maxlength=12 name='titlefontsize' value=\""
				. $go_data['settings']['[TITLE.FONTSIZE]'] . "\">" . __('eg. 0.8em','gallery-objects') . "\n";
			echo "</td></tr>\n";

			echo "<tr id='go-as-5' style='display: none;'><td valign=top align=right>";
			_e('Title Font Family','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=10 maxlength=255 name='titlefontfamily' value='"
				. stripslashes(stripslashes($go_data['settings']['[TITLE.FONTFAMILY]']))
				. "'>" . __('Arial, Verdana','gallery-objects') . "\n";
			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Vertical Image Align','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<select name='imagevalign'>\n";

			echo "  <option value='top'";
			if ($go_data['settings']['[IMAGEWRAPPER.VALIGN]']=='top') echo " selected";
			echo ">";
			_e('Top','gallery-objects');
			echo "</option>\n";

			echo "  <option value='center'";
			if ($go_data['settings']['[IMAGEWRAPPER.VALIGN]']=='center') echo " selected";
			echo ">";
			_e('Center','gallery-objects');
			echo "</option>\n";

			echo "  <option value='bottom'";
			if ($go_data['settings']['[IMAGEWRAPPER.VALIGN]']=='bottom') echo " selected";
			echo ">";
			_e('Bottom','gallery-objects');
			echo "</option>\n";

			echo "</select>\n";

			echo "</td></tr>\n";

			echo "<tr id='go-as-6' style='display: none;'><td valign=top align=right>";
			_e('Outside Desc Width','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=4 maxlength=4 name='descriptionwidth' value='"
				. stripslashes($go_data['settings']['[DESCRIPTION.WIDTH]'])
				. "'>";

			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Outside Desc Background','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=8 maxlength=8 class=color name='descriptionbackground' value='"
				. stripslashes($go_data['settings']['[DESCRIPTION.BACKGROUND]'])
				. "'>";
			echo "</td></tr>";

			echo "<tr id='go-as-7' style='display: none;'><td valign=top align=right>\n";
			_e('Outside Desc Height','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=text size=4 maxlength=4 name='descriptionheight' value='"
				. stripslashes($go_data['settings']['[DESCRIPTION.HEIGHT]'])
				. "'>";

//			echo "</td></tr><tr><td valign=top align=right>\n";

			echo "</td><td width=10></td><td valign=top align=right>\n";
			_e('Outside Desc Back Transparent','gallery-objects');
			echo "</td><td valign=top align=left> : ";
			echo "<input type=checkbox name='descriptiontransparent' value='true'";
			if ($go_data['settings']['[DESCRIPTION.TRANSPARENT]'] == 'true') echo " checked";
			echo "> ";
			_e('Overrides Above Color','gallery-objects');

			echo "</td></tr>\n";

			echo "<tr><td align=center colspan=2>\n";
			echo "<br />\n";
			echo "<input type=button value='Preview Settings' onclick=\"go_iframe_reload(this.form,'ifid','"
				. dirname($_SERVER['PHP_SELF']) . "/admin-ajax.php?action=go_view_object&viewid=" . $iid . "&type=html')\">\n";

			echo "</td><td width=10></td><td colspan=2 align=center>\n<br />\n";
			echo "<input id=\"go-button-save\" type=image onClick=\"go_save_view_settings(this.form); return false;\" src=\"";
			echo $go_folder . "images/save-settings.png\">\n";

			echo "</td></tr></table>\n";
			echo "</form>\n";

			// Shall we do an iframe here for the view now?
			echo "<iframe frameborder=0 id=ifid allowtransparency='true' width=100% height=800 "
				. "src=\"" . dirname($_SERVER['PHP_SELF']) . "/admin-ajax.php?action=go_view_object&viewid=$iid&type=html\">\n";
			echo "</iframe>\n";
			echo "<br /><br />\n";

			break;


		    default:
			
	?>

				<ul class="tabs">
					<li><a href="#" title="content_1" class="tab<?php if ($gotab==1) echo " active"; ?>"><?php _e('Manage Albums','gallery-objects'); ?></a></li>  
					<li><a href="#" title="content_2" class="tab<?php if ($gotab==2) echo " active"; ?>"><?php _e('Manage Views','gallery-objects'); ?></a></li>  
					<li><a href="#" title="content_3" class="tab<?php if ($gotab==3) echo " active"; ?>"><?php _e('Help, Support, & Updates','gallery-objects'); ?></a></li>
				</ul>
  
				<div id="content_1" class="content">
					<br />
					<div class="go-form">
						<?php _e('Create a new Album Here.','gallery-objects'); ?>
						<form method="post">
							<input type=hidden name="go-action" value="new-album">
							<table border=0 cellpadding=4 cellspacing=4><tr><td valign=top>
							<?php _e('Album Name','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top> <input type=text name="go-name" size=53 maxlength=64>
							</td></tr><tr><td valign=top>
							<?php _e('Album Description','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top> <textarea name="go-description" rows=3 cols=45></textarea>
							</td></tr>
	     						</table>
	     						<input type=submit value="<?php _e('Create Album','gallery-objects'); ?>">
						</form>
					</div>
					<br />
					<?php _e('Existing Albums','gallery-objects'); ?><br />

					<?php
						$albums = $wpdb->get_results("SELECT id, oname, value FROM $go_table_name WHERE 
							otype=1");
						$eo	= 'odd';
						foreach ($albums as $talbum) {
							$val = unserialize($talbum->value);
							?><div class="go-list-<?php echo $eo; ?>">
								<table border=0 cellpadding=2 cellspacing=2><tr><td valign=center width=180><nobr>
								<form method="post">
								<input type=hidden name="go-action" value="edit-album">
								<input type=hidden name="go-album-id" value="<?php echo $talbum->id; ?>">
								<?php
									//$pcnt = count($val['images']);
									//echo "Images [" . $pcnt . "]";
									//if ($pcnt>3) $pcnt = 3;
									$i=0;
									foreach ($val['images'] as $k => $v) {
										$pic = $wpdb->get_row("SELECT a.post_name, a.post_title, a.guid, b.meta_value
											FROM $wpdb->posts a, $wpdb->postmeta b WHERE a.ID=b.post_id AND
											a.ID=$k AND b.meta_key='_wp_attachment_metadata'");
										$meta = unserialize($pic->meta_value);
										$folder = _gofolder($meta['file']);
										echo '<img width="50" src="' . $wpup['baseurl'] . "/" . $folder . "/"
											. $meta['sizes']['thumbnail']['file'] . '">' . "&nbsp;";
										if (++$i > 2 ) break;
									}
								?>
								</nobr></td><td valign=center width=100%>
								<?php echo $talbum->id . " : " . $talbum->oname;
								?>
								</td><td valign=center width=150>
								<input type=submit value="Edit Album">
								</form>
								</td><td width=10>&nbsp;&nbsp;&nbsp;
								</td><td valign=center width=150>
								<form method="post">
								<input type=hidden name="go-action" value="delete-album">
								<input type=hidden name="go-album-id" value="<?php echo $talbum->id; ?>">
								<input type=submit value="<?php _e('Delete Album','gallery-objects'); ?>" onclick="return go_confirm_delete();">
								</form>
								</td></tr></table>
							</div>
							<?php if ($eo=="odd") $eo = "even"; else $eo = "odd";
						}
					?>
					<br />
				</div><!-- content_1 -->

				<div id="content_2" class="content">
					<br />
					<?php
					$golink = $wpdb->get_row("SELECT id FROM $go_table_name WHERE otype=1");
					if (!$golink) { ?>
						<div class="go-error">
						<?php _e('You can not define a view until you have created an album.','gallery-objects'); ?>
						</div><?php
					} else {
					?>
						<div class="go-form">
							<form method="post">
								<?php _e('Create a new View Here.','gallery-objects'); ?>
								<input type=hidden name="go-action" value="new-view">
								<table border=0 cellpadding=4 cellspacing=4><tr><td valign=top>
								<?php _e('View Name','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top> <input type=text name="go-name" size=53 maxlength=64>
								</td></tr><tr><td valign=top>
								<?php _e('View Type','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
								<select name="go-type">
									<option value="1">1 AD Gallery - Slideshow</option>
								</select>
								</td></tr><tr><td valign=top>
								<?php _e('Album for this View','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
								<select name="go-album">
								<?php
									$albums = $wpdb->get_results("SELECT id, oname, value FROM $go_table_name WHERE otype=1");
									foreach($albums as $album) {
										echo "<option value=\"" . $album->id . "\">" . $album->id . " - " . $album->oname . "</option>\n";
									}
								?>
								</select>
								</td></tr><tr><td valign=top>
								<?php _e('View Description','gallery-objects'); ?> </td><td valign=top>:</td><td valign=top>
								<textarea name="go-description" rows=3 cols=45></textarea>
								</td></tr></table>
								<input type=submit value="<?php _e('Create View','gallery-objects'); ?>">
							</form>
						</div>

						<br />
						<?php _e('Existing Views','gallery-objects'); ?><br />

						<?php
							$views = $wpdb->get_results("SELECT id, oname, value FROM $go_table_name WHERE
								otype=2");
							$eo = 'odd';
							foreach ($views as $view) {
								$val = unserialize($view->value);
								?><div class="go-list-<?php echo $eo; ?>">
									<table border=0 cellpadding=2 cellspacing=2><tr><td valign=center width=180>
									<nobr>
									<form method="post">
									<input type=hidden name="go-action" value="edit-view">
									<input type=hidden name="go-view-id" value="<?php echo $view->id; ?>">
									<?php
										$nm = split("\^",$view->oname);
										// How about we loop through the pics in Album here?
										$album = $wpdb->get_row("SELECT id, oname, value FROM $go_table_name "
											. "WHERE id=" . $nm[0]);
										if ($album) {
										    $val = unserialize($album->value);
										    $i = 0;
										    foreach ($val['images'] as $k => $v) {
											$pic = $wpdb->get_row("SELECT a.post_name, a.post_title, a.guid, "
												. "b.meta_value FROM $wpdb->posts a, $wpdb->postmeta b "
												. "WHERE a.ID=b.post_id AND a.ID=$k AND b.meta_key='"
												. "_wp_attachment_metadata'");
											$meta = unserialize($pic->meta_value);
											$folder = _gofolder($meta['file']);
											echo '<img width="50" src="' . $wpup['baseurl'] . "/" . $folder . "/"
												. $meta['sizes']['thumbnail']['file'] . '">' . "&nbsp;";
											if (++$i > 2) break;
										    }
										} else {
											echo "<font color=red>" . __('INVALID ALBUM ID','gallery-objects') . "</font>";
										}
									?>
									</nobr>
									</td><td valign=center width=100%>
									<?php
										echo "&nbsp; ".__('View ID','gallery-objects')." : ";
										echo $view->id . " <br />&nbsp; ".__('View Name','gallery-objects')." : " . $nm[1];
										echo "<br />&nbsp; ".__('Album Name (ID) : ','gallery-objects');
										if ($album) echo $album->oname;
										else echo "<font color=red>INVALID ALBUM ID</font>";
										echo " (" . $nm[0] . ")";
									?>
									</td><td valign=center width=150>
									<input type=submit value="<?php _e('Edit View','gallery-objects'); ?>">
									</form>
									</td><td width=10>&nbsp;&nbsp;&nbsp;
									</td><td valign=center width=150>
									<form method="post">
									<input type=hidden name="go-action" value="delete-view">
									<input type=hidden name="go-view-id" value="<?php echo $view->id; ?>">
									<input type=submit value="<?php _e('Delete View','gallery-objects'); ?>" onclick="return go_confirm_delete();">
									</form>
									</td></tr></table>
								</div>
								<?php
									if ($eo=="odd") $eo = "even"; else $eo = "odd";
							}

						?>

					<?php
					}
					?>
					


				</div><!-- content_2 -->

				<div id="content_3" class="content">
					<div class="go-form">
						<?php
						// Get Update Text, if newer version of Gallery Objects exists.
						echo "<div align=center id=\"go-check-for-update\">Checking for Updates...</div>\n";
						$ifile = _go_get_file(dirname(__FILE__) . "/html/help.support.updates.html");
						echo $ifile;
						?>
					</div>
				</div><!-- content_3 -->

			</div><!-- End of tabbed_area -->  
			<?php
			if ($gotab==2) { ?>
				<script type="text/javascript">
					jQuery('.content').slideUp();
					jQuery('#content_2').slideDown();
				</script>
			<?php } else if ($gotab==3) { ?>
				<script type="text/javascript">
					jQuery('.content').slideUp();
					jQuery('#content_3').slideDown();
					go_check_version();
				</script>
			<?php }
			break;
		}	// End of Switch Statement
		?>
	</div><!-- End of tabbed_box_1 -->
<?php

}

function _go_show_image_nav($link_text,$start_id=0,$iid,$filter_month="",$filter_text="") {
	?>
	<form method="post">
	<input type=hidden name="go-action" value="edit-album">
	<input type=hidden name="go-album-id" value="<?php echo $iid; ?>">
	<input type=hidden name="go-filter-month" value="<?php echo $filter_month; ?>">
	<input type=hidden name="go-filter-text" value="<?php echo $filter_text; ?>">
	<input type=hidden name="go-filter-paged" value="<?php echo $start_id; ?>">
	<input type=submit value="<?php echo $link_text; ?>">
	</form>
	<?php
}

function _goget($what,$default='',$notset='') {
    if ( (!isset($_POST[$what])) && (!isset($_GET[$what])) ) return $notset;

    if (isset($_POST[$what])) {
	if (strlen($_POST[$what])>0)	$tret = addslashes($_POST[$what]);
	else				$tret = $default;
    } else if (strlen($_GET[$what])>0)	$tret = addslashes($_GET[$what]);
    else                                $tret = $default;
    if (!isset($tret)) $tret = '';
    return $tret;
}

/*	The primary purpose of the _goget_view_settings functions was to
	ensure that as upgrades of this plugin are released, that any NEW
	view type settings are added to existing views.  This is why it
	also UPDATES the view settings if it finds missing settings.

	However, it can also be used when a particular view is being altered
	to a new view type.  In this case, it will provide the default settings
	of the viewtype.
 */

function _goget_view_settings($viewid=0,$viewtype=0) {
	GLOBAL $wpdb, $go_table_name, $go_defaults;

	$reset = 0;
	$update = 0;

	if ((!$viewid) && (!$viewtype)) return null;	// You MUST supply either a viewid OR a viewtype OR both

	if ($viewid) {					// Retreive Settings from DB
		$golink = $wpdb->get_row("SELECT * FROM $go_table_name WHERE id=$viewid");
		$go_data = unserialize($golink->value);
		if ($viewtype) {
			if ($viewtype!=$go_data['viewtype']) {	// Lets reset to the default settings
				$reset = 1;
				$go_data['viewtype'] = $viewtype;
			}
		}
		if (!$reset) {				// We should verify ALL settings are present

			if (!isset($go_defaults[$go_data['viewtype']])) return $go_data;			// Invalid View, Can't Verify
			foreach ($go_defaults[$go_data['viewtype']] as $go_key => $go_value) {
				if (!isset($go_data['settings'][$go_key])) {
					$go_data['settings'][$go_key] = $go_value;
					$update++;
				}
			}

		} else {		// What if this IS a reset?
					// Currently just do the same as if getting the default settings.

			if (!isset($go_defaults[$viewtype])) return null;
			foreach ($go_defaults[$viewtype] as $go_key => $go_value) {
				$go_data['settings'][$go_key] = $go_value;
			}
		}

	} else {		// Lets get the default settings for this view type.

		if (!isset($go_defaults[$viewtype])) return null;
		foreach ($go_defaults[$viewtype] as $go_key => $go_value) {
			$go_data['settings'][$go_key] = $go_value;
		}

	}

	if ($update) {		// There was missing parameters!
		$result = $wpdb->update($go_table_name,array('value'=>serialize($go_data)),array('id'=>$viewid) );
	}

	return $go_data['settings'];
}

function _gofolder($path) {
	$ipos = strrpos($path,'/');
	return substr($path,0,$ipos);
}

function _gofile($path) {
	$ipos = strrpos($path,'/');
	return substr($path,$ipos);
}

/*	Is there a VIEW defined for this ALBUM? */

function _go_album_has_view($album) {
	GLOBAL $wpdb, $go_table_name;

	$golink = $wpdb->get_row("SELECT id FROM $go_table_name WHERE otype=2 AND oname LIKE '" . $album . "^%'");
	if ($golink) return true;
	return false;
}

/*	Is this picture ($pic) already in this album ($album)?  */

function _go_pic_is_in_album($pic,$album) {
	GLOBAL $wpdb, $go_table_name;

	$golink = $wpdb->get_row("SELECT value FROM $go_table_name WHERE id=$album");
	$val = unserialize($golink->value);

	if (isset($val['images'][$pic])) return true;

	return false;	// Could not locate this image in the album.
}

/*	Show the Gallery to the Front End user if on this page	*/

function _go_show_object($content) {
	GLOBAL $wpdb, $go_table_name;

	$siteurl = get_option('siteurl');
	$wpup = wp_upload_dir();
	$go_folder = WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__));

	$galleries = preg_match("/\[(galobj.*)\]/i",$content,$matches);

	if ($galleries) {

		// only ONE gallery supported per content area for now.
		// Get the viewid

		$i = stripos($matches['1'],'viewid');
		$viewid	= substr($matches['1'],$i+7);

		$golink = $wpdb->get_row("SELECT * FROM $go_table_name WHERE id=" . $viewid);
		if (!$golink) {
			$new = preg_replace("/\[".$matches['1']."\]/","GALOBJ DB FAIL",$content);
			return $new;
		}

		$val = unserialize($golink->value);

		// Lets just display the iframe

		$height = $val['settings']['[CONTAINER.HEIGHT]'] + 55;		// 55 = ((container padding:30)) + (adcontrolheight:20)
		if ($val['settings']['[THUMB.HEIGHT]']!="") $height += $val['settings']['[THUMB.HEIGHT]'];
		else $height += 150;

		$fld = dirname($_SERVER['PHP_SELF']);
		$flen = strlen($fld);
		if ($flen) { if ($fld[$flen-1]!='/') $fld .= "/"; }

		$out = "<iframe frameborder=0 id=ifid width=\"" . ($val['settings']['[CONTAINER.WIDTH]']+60) . "\""
			. " height=\"" . $height . "\" allowtransparency=\"true\" src=\""
			. $fld . "wp-admin/admin-ajax.php?action=go_view_object&viewid="
			. $viewid . "&type=html\"></iframe>\n";

		$new = preg_replace("/\[".$matches['1']."\]/",$out,$content);

	} else {
		$new = $content;
	}

	return $new;

}

/*	Defined as AJAX ONLY to call separately from WordPress :)  Technically not Ajax, though called from an iframe	*/

function _go_view_object() {
	GLOBAL $wpdb, $go_table_name;

	$viewid = _goget('viewid');
	$objtype = _goget('type','js','js');
	$gopreview = _goget('GOPREVIEW',0,0);
	$dalign	= false;

	$contt = _goget('TRANSPARENTBACKGROUND','','NOTSET');
	$desct = _goget('DESCRIPTIONTRANSPARENT','','NOTSET');
	$scrol = _goget('SCROLLCOLOR');

	$siteurl = get_option('siteurl');
	$wpup = wp_upload_dir();
	$go_folder = WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__));

	$golink = $wpdb->get_row("SELECT * FROM $go_table_name WHERE id=" . $viewid);
	if (!$golink) { echo '0'; die(); }
	$val = unserialize($golink->value);


	$val['settings']['[GO.VIEW.ID]'] = $viewid;
	$val['settings']['[GO.AJAX.URL]'] = dirname($_SERVER['PHP_SELF']) . "/admin-ajax.php";
	$val['settings']['[GO.IMAGE.URL]'] = $go_folder . "images/"; 

	if ($scrol!="") $val['settings']['[SCROLL.COLOR]'] = $scrol;

	if ($gopreview) {
		if ($contt=="true")     $val['settings']['[TRANSPARENT.BACKGROUND]'] = "true";
		else                    $val['settings']['[TRANSPARENT.BACKGROUND]'] = "false";
		if ($desct=="true")	$val['settings']['[DESCRIPTION.TRANSPARENT]'] = "true";
		else			$val['settings']['[DESCRIPTION.TRANSPARENT]'] = "false";
		$val['settings']['[CONTROL.FONTCOLOR]']		= _goget('CONTROLFONTCOLOR');
		$val['settings']['[CONTROL.FONTSIZE]']		= _goget('CONTROLFONTSIZE');
		$val['settings']['[CONTROL.FONTFAMILY]']	= _goget('CONTROLFONTFAMILY');
		$val['settings']['[DESCRIPTION.FONTCOLOR]']	= _goget('DESCRIPTIONFONTCOLOR');
		$val['settings']['[DESCRIPTION.FONTSIZE]']	= _goget('DESCRIPTIONFONTSIZE');
		$val['settings']['[DESCRIPTION.FONTFAMILY]']	= _goget('DESCRIPTIONFONTFAMILY');
		$val['settings']['[TITLE.FONTCOLOR]']		= _goget('TITLEFONTCOLOR');
		$val['settings']['[TITLE.FONTSIZE]']		= _goget('TITLEFONTSIZE');
		$val['settings']['[TITLE.FONTFAMILY]']		= _goget('TITLEFONTFAMILY');

		$val['settings']['[THUMB.HEIGHT]']              = _goget('THUMBHEIGHT');
		$val['settings']['[CONTAINER.HEIGHT]']		= _goget('CONTAINERHEIGHT');
		$val['settings']['[CONTAINER.WIDTH]']		= _goget('CONTAINERWIDTH');
		$val['settings']['[CONTAINER.BACKGROUND]']	= _goget('CONTAINERBACKGROUND');
		$val['settings']['[TRANS.TIME]']		= _goget('TRANSTIME');
		$val['settings']['[TRANS.SPEED]']		= _goget('TRANSSPEED');
		$val['settings']['[TRANS.EFFECT]']		= _goget('TRANSEFFECT');
		$val['settings']['[DISPLAY.TITLE]']		= _goget('DISPLAYTITLE');
		$val['settings']['[DISPLAY.DESCRIPTION]']	= _goget('DISPLAYDESCRIPTION');
		$val['settings']['[DESCRIPTION.ALIGN]']		= _goget('DESCRIPTIONALIGN');
		$val['settings']['[IMAGEWRAPPER.VALIGN]']	= _goget('IMAGEWRAPPERVALIGN');
		$val['settings']['[DISPLAY.LINKFROM]']		= _goget('DISPLAYLINKFROM');
		$val['settings']['[AUTO.START]']		= _goget('AUTOSTART');
		$val['settings']['[NAV.LOCATION]']		= _goget('NAVLOCATION');
		$val['settings']['[DESCRIPTION.WIDTH]']		= _goget('DESCRIPTIONWIDTH');
		$val['settings']['[DESCRIPTION.HEIGHT]']	= _goget('DESCRIPTIONHEIGHT');
		$val['settings']['[DESCRIPTION.BACKGROUND]']	= _goget('DESCRIPTIONBACKGROUND');
	}

	if ($val['settings']['[TRANSPARENT.BACKGROUND]']=="true") {
		$val['settings']['[BACKGROUND.TRANSPARENT]'] = "background: transparent;";
		$val['settings']['[CONTAINER.BACKGROUND]'] = "transparent";
	} else {
		$val['settings']['[CONTAINER.BACKGROUND]'] = "#" . $val['settings']['[CONTAINER.BACKGROUND]'];
	}

	if ($val['settings']['[DESCRIPTION.TRANSPARENT]']=="true") {
		$val['settings']['[DESCRIPTION.BACKGROUND]'] = "transparent";
	} else {
		$val['settings']['[DESCRIPTION.BACKGROUND]'] = "#" . $val['settings']['[DESCRIPTION.BACKGROUND]'];
	}

	switch ($val['settings']['[DESCRIPTION.ALIGN]']) {
		case 'outsideleft':
			$val['settings']['[DESCRIPTION.WRAPPER]']	= "$('#galobj-descriptions')";
			$val['settings']['[OUTSIDE.DESCRIPTION.ALIGN]']	= "style=\"float:left;\"";
			$val['settings']['[IMAGEWRAPPER.ALIGN]']	= "style=\"float:right;\"";
			$val['settings']['[DESCRIPTION.ALIGN]']		= "top";
			$dalign="outsideleft";
			if ($val['settings']['[DESCRIPTION.WIDTH]']=='') {
				$val['settings']['[DESCRIPTION.WIDTH]'] = ($val['settings']['[CONTAINER.WIDTH]'] / 2);
				$val['settings']['[DESCRIPTION.WIDTH]'] = '100';
			}
			if ($val['settings']['[DESCRIPTION.HEIGHT]']=='') {
				$val['settings']['[DESCRIPTION.HEIGHT]'] = ($val['settings']['[CONTAINER.HEIGHT]'] / 2);
				$val['settings']['[DESCRIPTION.HEIGHT]'] = '100';
			}
			$val['settings']['[IMAGEWRAPPER.WIDTH]'] = ($val['settings']['[CONTAINER.WIDTH]'] - $val['settings']['[DESCRIPTION.WIDTH]'] - 45) . "px";
			break;
		case 'outsideright':
			$val['settings']['[DESCRIPTION.WRAPPER]']       = "$('#galobj-descriptions')";
			$val['settings']['[OUTSIDE.DESCRIPTION.ALIGN]']	= "style=\"float:right;\"";
			$val['settings']['[IMAGEWRAPPER.ALIGN]']	= "style=\"float:left;\"";
			$val['settings']['[DESCRIPTION.ALIGN]']         = "top";
			$dalign="outsideright";
			if ($val['settings']['[DESCRIPTION.WIDTH]']=='') {
				$val['settings']['[DESCRIPTION.WIDTH]'] = ($val['settings']['[CONTAINER.WIDTH]'] / 2);
				$val['settings']['[DESCRIPTION.WIDTH]'] = '100';
			}
			if ($val['settings']['[DESCRIPTION.HEIGHT]']=='') {
				$val['settings']['[DESCRIPTION.HEIGHT]'] = ($val['settings']['[CONTAINER.HEIGHT]'] / 2);
				$val['settings']['[DESCRIPTION.HEIGHT]'] = '100';
			}
			$val['settings']['[IMAGEWRAPPER.WIDTH]'] = ($val['settings']['[CONTAINER.WIDTH]'] - $val['settings']['[DESCRIPTION.WIDTH]'] - 45) . "px";
			break;
		case 'outsidetop':
			$dalign="outsidetop";
			$val['settings']['[DESCRIPTION.WRAPPER]']       = "$('#galobj-descriptions')";
			$val['settings']['[OUTSIDE.DESCRIPTION.ALIGN]'] = "style=\"margin-left: auto; margin-right: auto;\"";
			$val['settings']['[IMAGEWRAPPER.ALIGN]']	= "";
			$val['settings']['[DESCRIPTION.ALIGN]']		= "top";
			break;
		case 'outsidebottom':
			$dalign="outsidebottom";
			$val['settings']['[DESCRIPTION.WRAPPER]']       = "$('#galobj-descriptions')";
			$val['settings']['[OUTSIDE.DESCRIPTION.ALIGN]'] = "style=\"margin-left: auto; margin-right: auto;\"";
			$val['settings']['[IMAGEWRAPPER.ALIGN]']	= "";
			$val['settings']['[DESCRIPTION.ALIGN]']		= "top";
			break;
		default:
			$val['settings']['[OUTSIDE.DESCRIPTION.ALIGN]'] = "";
			$val['settings']['[IMAGEWRAPPER.ALIGN]']	= "";
			break;
	}


	if ($val['settings']['[CONTROL.FONTCOLOR]']!="")
		$val['settings']['[CONTROL.FONTCOLOR]'] = "color: #" . $val['settings']['[CONTROL.FONTCOLOR]'] . ";";

	if ($val['settings']['[CONTROL.FONTSIZE]']!="")
		 $val['settings']['[CONTROL.FONTSIZE]'] = "font-size: " . $val['settings']['[CONTROL.FONTSIZE]'] . ";";

	if ($val['settings']['[CONTROL.FONTFAMILY]']!="")
		$val['settings']['[CONTROL.FONTFAMILY]'] = "font-family: " . $val['settings']['[CONTROL.FONTFAMILY]'] . ";";

	if ($val['settings']['[DESCRIPTION.FONTCOLOR]']!="")
		$val['settings']['[DESCRIPTION.FONTCOLOR]'] = "color: #" . $val['settings']['[DESCRIPTION.FONTCOLOR]'] . ";";

	if ($val['settings']['[DESCRIPTION.FONTSIZE]']!="")
		$val['settings']['[DESCRIPTION.FONTSIZE]'] = "font-size: " . $val['settings']['[DESCRIPTION.FONTSIZE]'] . ";";

	if ($val['settings']['[DESCRIPTION.FONTFAMILY]']!="")
		$val['settings']['[DESCRIPTION.FONTFAMILY]'] = "font-family: " . $val['settings']['[DESCRIPTION.FONTFAMILY]'] . ";";

	if ($val['settings']['[TITLE.FONTCOLOR]']!="")
		$val['settings']['[TITLE.FONTCOLOR]'] = "color: #" . $val['settings']['[TITLE.FONTCOLOR]'] . ";";

	if ($val['settings']['[TITLE.FONTSIZE]']!="")
		$val['settings']['[TITLE.FONTSIZE]'] = "font-size: " . $val['settings']['[TITLE.FONTSIZE]'] . ";";

	if ($val['settings']['[TITLE.FONTFAMILY]']!="")
		$val['settings']['[TITLE.FONTFAMILY]'] = "font-family: " . $val['settings']['[TITLE.FONTFAMILY]'] . ";";	

	if ($val['settings']['[THUMB.HEIGHT]']) $val['settings']['[THUMB.HEIGHT.TEXT]'] = "height=" . $val['settings']['[THUMB.HEIGHT]'];
	else $val['settings']['[THUMB.HEIGHT.TEXT]'] = "";

	list($go_aid, $go_name) = split("\^",$golink->oname);

	if ($val['viewtype']==1) {
		if ($objtype=='js') {
			header('Content-Type: text/javascript;',false);
			$ifile = _go_get_file(dirname(__FILE__) . "/js/iframe.1.js",$val['settings']);
			echo $ifile;
		} else if ($objtype=="css") {		// End of objtype=js
			header("Content-Type: text/css");
			$ifile = _go_get_file(dirname(__FILE__) . "/css/iframe.1.css",$val['settings']);
			echo $ifile;
		} else if ($objtype=="html") {           // End of objtype=css
//			header('Content-Type: text/javascript;',true);

			// Display the top of the HTML

			echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">\n";
			echo "<html><head></head>\n";
			echo "<style type=\"text/css\">\n";
			echo "body {\n";
			echo "  padding: 0px;\n";
			if ($val['settings']['[TRANSPARENT.BACKGROUND]']=='true') {
				echo "  background: transparent;\n";
			} else {
				echo "  background: " . $val['settings']['[CONTAINER.BACKGROUND]'] . ";\n";
			}
			echo "  margin: 0px;\n";
			echo "}\n";
			echo "</style>\n";
			echo "<body>\n";
			$ifile = _go_get_file(dirname(__FILE__) . "/html/view.1.top",$val['settings']);
			echo $ifile;

			if ($val['settings']['[NAV.LOCATION]'] == 'top') {
				
				$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.nav.html",$val['settings']);
				echo $ifile;
			} else if ($val['settings']['[NAV.LOCATION]'] == 'none') {
				echo "\t<div class=\"galobj-ad-nav\" style=\"display:none\">\n";
				echo "\t\t<div class=\"galobj-ad-thumbs\" style=\"display:none\">\n";
				echo "\t\t\t<ul class=\"galobj-ad-thumb-list\" style=\"display:none\">\n";
			} else {
				if (($dalign=="outsideleft")||($dalign=="outsidetop")) {
					$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.desc.html",$val['settings']);
					echo $ifile;
					$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.pics.html",$val['settings']);
					echo $ifile;
					echo "\t<div style=\"clear:both;\"></div>\n";
				} else if (($dalign=="outsideright")||($dalign=="outsidebottom")) {
					$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.pics.html",$val['settings']);
					echo $ifile;
					$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.desc.html",$val['settings']);
					echo $ifile;
					echo "\t<div style=\"clear:both;\"></div>\n";
				} else {
					$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.pics.html",$val['settings']);
					echo $ifile;
				}
				$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.nav.html",$val['settings']);
				echo $ifile;
			}

			$go_alink = $wpdb->get_row("SELECT oname, value FROM $go_table_name WHERE id=" . $go_aid);
			$tval = unserialize($go_alink->value);

			$cnt = 0;
			foreach ($tval['images'] as $k => $v) {

				// post_content = description
				// post_excerpt = caption
				// Search for metakey = '_wp_attachment_image_alt' to get the Alternate Image Text


				$cnt++;

				$pic = $wpdb->get_row("SELECT a.post_name, a.post_content, a.post_title, a.post_excerpt, a.guid, b.meta_value FROM 
					$wpdb->posts a, $wpdb->postmeta b WHERE a.ID=b.post_id AND a.ID=$k AND
					b.meta_key='_wp_attachment_metadata'");
				$meta = unserialize($pic->meta_value);
				$folder = _gofolder($meta['file']);
				echo "<li>\n";
				echo "	<a href=\"" . $wpup['baseurl'] . "/" . $folder . "/";
				if (isset($meta['sizes']['large'])) echo  $meta['sizes']['large']['file'] . "\">\n";
				else echo _gofile($meta['file']) . "\">\n";

//				else if (isset($meta['sizes']['medium'])) echo $meta['sizes']['medium']['file'] . "\">\n";
//				else echo $meta['sizes']['thumbnail']['file'] . "\">\n";

				echo "		<img ";
				if ($val['settings']['[THUMB.HEIGHT]']!="") echo "height=" . $val['settings']['[THUMB.HEIGHT]'] . " ";
				echo "src=\"" . $wpup['baseurl'] . "/" . $folder . "/" . $meta['sizes']['thumbnail']['file'] . "\"";

				if ($val['settings']['[DISPLAY.TITLE]']=='true') echo " title=\"" . $pic->post_title . "\"";
				if ($val['settings']['[DISPLAY.DESCRIPTION]']=='true') echo " alt=\"" . $pic->post_content . "\"";

				if ($val['settings']['[DISPLAY.LINKFROM]']=='alt') {
					$pica = $wpdb->get_row("SELECT meta_value FROM $wpdb->postmeta WHERE post_id=$k AND meta_key='_wp_attachment_image_alt'");
					if ($pica) echo " longdesc=\"" . $pica->meta_value . "\"";
				} else if ($val['settings']['[DISPLAY.LINKFROM]']=='caption') {
					echo " longdesc=\"" . $pic->post_excerpt . "\"";
				}

				echo " class=\"goimage\">\n";
				echo "	</a>\n";
				echo "</li>\n";
			}

			$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.foot.nav.html",$val['settings']);
			echo $ifile;

			if ($val['settings']['[NAV.LOCATION]'] != 'bottom') {
				$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.adcontrols.html",$val['settings']);
				echo $ifile;
				$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.head.pics.html",$val['settings']);
				echo $ifile;
			} else {
				$ifile = _go_get_file(dirname(__FILE__) . "/html/iframe.1.adcontrols.html",$val['settings']);
				echo $ifile;
			}


			$ifile = _go_get_file(dirname(__FILE__) . "/html/view.1.bottom",$val['settings']);
			echo $ifile;
			echo "</body></html>\n";
		}				// End of objtype=html
	}				// End of viewtype = 1

	die();
}

/*	Read file into variable					*/

function _go_get_file($fname,$filter=array('GO.NOTHING'=>'')) {
//	echo "Getting File [" . $fname . "]<br />\n";
	$lin = "";
	$handle = @fopen($fname,"r");
	if (!$handle) return false;
	while (!feof($handle)) {
		$lin .= fgets($handle,4096);
	}
	@fclose($handle);

	$line = _go_filter_line($lin,$filter);

	return $line;
}

/*	Filter / Replace variables in one line of text		*/

function _go_filter_line($line,$filter=array('GO.NOTHING'=>'')) {

	foreach ($filter as $k => $v) {
		$in[]	= $k;
		$out[]	= $v;
	}

	$retval = str_replace($in,$out,$line);
	return $retval;
}

/*	Ajax function to Add/Remove Images To/From Albums	*/

function _go_album_mod() {
	GLOBAL $wpdb, $go_table_name;

	$func	= _goget('go_func');
	$iid	= _goget('imgid');
	$aid	= _goget('albmid');

	$golink	= $wpdb->get_row("SELECT value FROM $go_table_name WHERE id=$aid");
	if (!$golink) { echo 'zero'; die(); }
	$val	= unserialize($golink->value);
	switch ($func) {
		case 'add':
			if (!isset($val['images'][$iid])) {
				$val['images'][$iid] = array();
				$rows_effected = $wpdb->update($go_table_name,array('value'=>serialize($val)), array('id'=>$aid));
				echo $rows_effected;
			} else echo '1';
			break;

		case 'remove':
			if (isset($val['images'][$iid])) {
				unset($val['images'][$iid]);
				$rows_effected = $wpdb->update($go_table_name,array('value'=>serialize($val)), array('id'=>$aid));
				echo $rows_efected;
			} else echo '1';
			break;

		default: echo '0'; die();
	}
 
	die();
}

/*	Check for updates	*/

function _go_check_for_updates() {
	GLOBAL $current_version;

	$result = wp_remote_fopen("http://galleryobjects.com/check.for.updates.php?version=".$current_version);
	echo $result;
	die();	
}

/*	Ajax function to Save View Settings to Album	*/

function _go_save_view_settings() {
	GLOBAL $wpdb, $go_table_name;

	$viewid	= _goget('viewid');
//	if (!$viewid) { echo 0; die(); }
	if (!$viewid) { echo 'no view id'; die(); }
	
	$golink = $wpdb->get_row("SELECT value FROM $go_table_name WHERE id=$viewid");
//	if (!$golink) { echo 0; die(); }
	if (!$golink) { echo 'no value from db'; die(); }
	$val = unserialize($golink->value);

	$trnst	= _goget('trans_time',0,0);
	if ($trnst) $trnst = ($trnst*1000);
	else $trnst = $val['settings']['[TRANS.TIME]'];

	$trnss	= _goget('trans_speed',0,0);
	if ($trnss) $trnss = ($trnss*1000);
	else $trnss = $val['settings']['[TRANS.SPEED]'];

	$val['settings']['[TRANS.TIME]'] = $trnst;
	$val['settings']['[TRANS.SPEED]'] = $trnss;
	$val['settings']['[TRANS.EFFECT]'] = _goget('trans_effect',$val['settings']['[TRANS.EFFECT]']);
	$val['settings']['[CONTAINER.WIDTH]'] = _goget('container_width',$val['settings']['[CONTAINER.WIDTH]']);
	$val['settings']['[CONTAINER.HEIGHT]'] = _goget('container_height',$val['settings']['[CONTAINER.HEIGHT]']);
	$val['settings']['[CONTAINER.BACKGROUND]'] = _goget('container_background',$val['settings']['[CONTAINER.BACKGROUND]']);
	$val['settings']['[TRANSPARENT.BACKGROUND]'] = _goget('transparent_background',$val['settings']['[TRANSPARENT.BACKGROUND]']);
	$val['settings']['[DESCRIPTION.TRANSPARENT]'] = _goget('description_transparent',$val['settings']['[DESCRIPTION.TRANSPARENT]']);
	$val['settings']['[DESCRIPTION.BACKGROUND]'] = _goget('description_background',$val['settings']['[DESCRIPTION.BACKGROUND]']);
	$val['settings']['[DESCRIPTION.ALIGN]'] = _goget('desc_align',$val['settings']['[DESCRIPTION.ALIGN]']);
	$val['settings']['[IMAGEWRAPPER.VALIGN]'] = _goget('imagewrapper_valign',$val['settings']['[IMAGEWRAPPER.VALIGN]']);
	$val['settings']['[DISPLAY.TITLE]'] = _goget('display_title',$val['settings']['[DISPLAY.TITLE]']);
	$val['settings']['[DISPLAY.DESCRIPTION]'] = _goget('display_description',$val['settings']['[DISPLAY.DESCRIPTION]']);
	$val['settings']['[DISPLAY.LINKFROM]'] = _goget('display_link_from',$val['settings']['[DISPLAY.LINKFROM]']);
	$val['settings']['[AUTO.START]'] = _goget('auto_start',$val['settings']['[AUTO.START]']);
	$val['settings']['[NAV.LOCATION]'] = _goget('nav_location',$val['settings']['[NAV.LOCATION]']);
	$val['settings']['[THUMB.HEIGHT]'] = _goget('thumbnail_height','','');
	$val['settings']['[SCROLL.COLOR]'] = _goget('scroll_color',$val['settings']['[SCROLL.COLOR]']);
	$val['settings']['[TRANSPARENT.BACKGROUND]'] = _goget('transparent_background',$val['settings']['[TRANSPARENT.BACKGROUND]']);
	$val['settings']['[CONTROL.FONTCOLOR]'] = _goget('ctrl_font_color',$val['settings']['[CONTROL.FONTCOLOR]']);
	$val['settings']['[CONTROL.FONTSIZE]'] = _goget('ctrl_font_size','','');
	$val['settings']['[CONTROL.FONTFAMILY]'] = _goget('ctrl_font_family','','');
	$val['settings']['[DESCRIPTION.FONTCOLOR]'] = _goget('desc_font_color',$val['settings']['[DESCRIPTION.FONTCOLOR]']);
	$val['settings']['[DESCRIPTION.FONTSIZE]'] = _goget('desc_font_size','','');
	$val['settings']['[DESCRIPTION.FONTFAMILY]'] = _goget('desc_font_family','','');
	$val['settings']['[TITLE.FONTCOLOR]'] = _goget('titl_font_color','','');
	$val['settings']['[TITLE.FONTSIZE]'] = _goget('titl_font_size','','');
	$val['settings']['[TITLE.FONTFAMILY]'] = _goget('titl_font_family','','');
	$val['settings']['[DESCRIPTION.WIDTH]'] = _goget('outside_desc_width','','');
	$val['settings']['[DESCRIPTION.HEIGHT]'] = _goget('outside_desc_height','','');

	$result = $wpdb->update($go_table_name,array('value'=>serialize($val)),array('id'=>$viewid) );
//	if (!$result) echo '0';
//	if (!$result) echo 'failed [' . serialize($val) . ']' . "\n\n";
//	if (!$result) $wpdb->print_error();
//	else echo '1';
	echo '1';
	die();
}

?>
