<?php
/*
Plugin Name: BP-Registration-Options
Plugin URI: http://webdevstudios.com/support/wordpress-plugins/buddypress-registration-options/
Description: BuddyPress plugin that allows for new member moderation, if moderation is switched on any new members will be blocked from interacting with any buddypress elements (except editing their own profile and uploading their avatar) and will not be listed in any directory until an admin approves or denies their account. Plugin also allows new members to join one or more predefined groups or blogs at registration.
Version: 1.0
Author: Brian Messenlehner of WebDevStudios.com
Author URI: http://webdevstudios.com/about/brian-messenlehner/
*/

add_action('admin_menu', 'bprwg_menu');
function bprwg_menu() {
  add_options_page('BP Registration Options', 'BP Registration Options', 8, __FILE__, 'bprwg_options');
}
//ADMIN SETTINGS PAGE
function bprwg_options() {
	global $wpdb;
	global $bp;
	?>
    <h2>BuddyPress Registration Options</h2>
    <?php
	switch_to_blog(1);
	$blog_id=get_current_site()->id;
	$iprefix=$wpdb->prefix;
	$iprefix=str_replace("_".$blog_id,"",$iprefix);
	$get_groups=get_option('bprwg_groups');
	$bp_groups = explode(',', $get_groups);
	$get_blogs=get_option('bprwg_blogs');
	$bp_blogs = explode(',', $get_blogs);
	$bp_moderate=get_option('bprwg_moderate');

	if($_POST['Save']!=""){

		//nonce WP security check
		check_admin_referer('cro_check');

		//Save Options
		$bp_groups=$_POST['bp_groups'];
		$bp_groups_str = implode(",", $bp_groups);
		update_option('bprwg_groups', $bp_groups_str);
		$bp_blogs=$_POST['bp_blogs'];
		$bp_blogs_str = implode(",", $bp_blogs);
		update_option('bprwg_blogs', $bp_blogs_str);
		$bp_moderate=$_POST['bp_moderate'];
		update_option('bprwg_moderate', $bp_moderate);
		$activate_message=$_POST['activate_message'];
		update_option('bprwg_activate_message', $activate_message);
		$approved_message=$_POST['approved_message'];
		update_option('bprwg_approved_message', $approved_message);
		$denied_message=$_POST['denied_message'];
		update_option('bprwg_denied_message', $denied_message);
		echo "<div id=message class=updated fade>Options Saved!</div>";
	}
	if($_POST['reset_messages']!=""){
		delete_option('bprwg_activate_message');
		delete_option('bprwg_approved_message');
		delete_option('bprwg_denied_message');
	}
	//ADMIN MODERATION ACTION*********************************************
	if($bp_moderate=="yes"){
		$view=$_GET['view'];
		//Moderation Actions*******************************************
		if ($view=="members" && $_POST['Moderate']!=""){
			$moderate_action=$_POST['Moderate'];
			$bp_member_check=$_POST['bp_member_check'];
			if($moderate_action=="Approve" && $bp_member_check!=""){
				$groups="";
				foreach ($bp_groups as $value) {
    				$groups=$groups.",".$value;
				}
				for ($i = 0; $i < count($bp_member_check); ++$i) {
					$userid=$bp_member_check[$i];
					$user_info = get_userdata($userid);
					$username=$user_info->user_login;
					$useremail=$user_info->user_email;
					//update any requested private groups
					if ($groups!=""){
						$group_email="";
						$groups="00".$groups;
						$sql="select a.id,a.name,b.slug from ".$iprefix."bp_groups a, ".$iprefix."bp_groups_members b where a.id=b.group_id and b.user_id=$userid and a.id in (".$groups.") and a.status in ('semi','private') and b.is_confirmed=0";
						$rs2 = mysql_query($sql);
						if (mysql_num_rows($rs2) > 0) {
							while ($r2 = mysql_fetch_assoc($rs2)) {
								$group_id = $r2['id'];
								$group_name = $r2['name'];
								$group_slug = $r2['slug'];
								$group_radio=$_POST["usergroup_".$userid."_".$group_id];
								if ($group_radio=="approve"){
									$sql="update ".$iprefix."bp_groups_members set is_confirmed=1 where group_id=$group_id and user_id=$userid";
									$wpdb->query($wpdb->prepare($sql));
									$group_email.="You have been accepted to the group [".$group_name."] - ".get_bloginfo("url")."/groups/".$slug."/.\n\n";
								}elseif ($group_radio=="deny"){
									$sql="delete from ".$iprefix."bp_groups_members where group_id=$group_id and user_id=$userid";
									$wpdb->query($wpdb->prepare($sql));
									$group_email.="Sorry but you were not accepted to the group [".$group_name."] - ".get_bloginfo("url")."/groups/".$slug."/.\n\n";
								}elseif ($group_radio=="ban"){
									$sql="update ".$iprefix."bp_groups_members set is_banned=1 where group_id=$group_id and user_id=$userid";
									$wpdb->query($wpdb->prepare($sql));
									$group_email.="Sorry but you were not accepted to the group [".$group_name."] - ".get_bloginfo("url")."/groups/".$slug."/.\n\n";
								}
							}
						}
					}
					update_usermeta($userid, 'bprwg_status', 'approved');
					$sql="update ".$iprefix."users set deleted=0 where ID=$userid";
					$wpdb->query($wpdb->prepare($sql));
					//email member with custom message
					$approved_message=get_option('bprwg_approved_message');
					$the_email=$approved_message;
					$the_email=str_replace("[username]",$username,$the_email);
					//$the_email="Hi ".$username.",\n\nYour member account on ".get_bloginfo("url")." has been approved! You can now login and start interacting with the rest of the community...";
					if($group_email!=""){
						$the_email.="\n\nThe following information pertains to each group you requested to join:\n\n".$group_email;
					}
					wp_mail($useremail, 'Membership Approved', $the_email);
				}
				echo "<div id=message class=updated fade>Checked Members Approved!</div>";
			}elseif($moderate_action=="Deny" && $bp_member_check!=""){
				for ($i = 0; $i < count($bp_member_check); ++$i) {
					$userid=(int)$bp_member_check[$i];
					$user_info = get_userdata($userid);
					$username=$user_info->user_login;
					$useremail=$user_info->user_email;
					update_usermeta($userid, 'bprwg_status', 'denied');
					wp_delete_user($userid);
					$sql="delete from ".$iprefix."users where ID=$userid";
					$wpdb->query($wpdb->prepare($sql));
					//email member with custom message
					$denied_message=get_option('bprwg_denied_message');
					$the_email=$denied_message;
					$the_email=str_replace("[username]",$username,$the_email);
					wp_mail($useremail, 'Membership Denied', $the_email);
				}
				echo "<div id=message class=updated fade>Checked Members Denied and Deleted!</div>";
			}else{
				echo "<div id=message class=updated fade>Please check at least 1 checkbox before pressing an action button!</div>";
			}
		}
		$sql="Select a.* from ".$iprefix."users a LEFT OUTER JOIN ".$iprefix."usermeta b on a.ID=b.user_id where b.meta_key='bprwg_status' and meta_value<>'approved' and meta_value<>'denied' order by a.ID";
		$rs = mysql_query($sql);
		$members_count=mysql_num_rows($rs);?>
    	<a <?php if ($view==""){?>style="font-weight:bold;"<?php } ?> href="<?php echo add_query_arg ('view', '');?>">Settings</a> |
        <a <?php if ($view=="members"){?>style="font-weight:bold;"<?php } ?> href="<?php echo add_query_arg ('view', 'members');?>">New Member Requests (<?php echo $members_count;?>)</a>
        <br /><br />
    <?php }
//ADMIN SETTINGS PAGE FORM*********************************************?>
    <form name="bpro" method="post">
    <?php
    if ( function_exists('wp_nonce_field') ) wp_nonce_field('cro_check');

    if ($view==""){
		$activate_message=get_option('bprwg_activate_message');
		if ($activate_message==""){
			$activate_message="<strong>Your membership account is awaiting approval by the site administrator.</strong> You will not be able to fully interact with the social aspects of this website until your account is approved. Once approved or denied you will receive an email notice.";
		}
		$approved_message=get_option('bprwg_approved_message');
		if ($approved_message==""){
			$approved_message="Hi [username],\n\nYour member account on ".get_bloginfo("url")." has been approved! You can now login and start interacting with the rest of the community...";
		}
		$denied_message=get_option('bprwg_denied_message');
		if ($denied_message==""){
			$denied_message="Hi [username],\n\nWe regret to inform you that your member account on ".get_bloginfo("url")." has been denied...";
		}
		?>
       	&nbsp;<input type="checkbox" id="bp_moderate" name="bp_moderate" onclick="show_messages();" value="yes"  <?php if($bp_moderate=="yes"){?>checked<?php }?>/>&nbsp;<strong>Moderate New Members</strong> (Every new member will have to be approved by an administrator.)<br />
       	<div id="bp_messages" style="display:none;">
        	<table>
            <tr>
           		<td align="right" valign="top">Activate & Profile Alert Message:</td>
            	<td><textarea name="activate_message" style="width:500px;height:100px;"><?php echo $activate_message;?></textarea></td>
            </tr>
            <tr>
           		<td align="right" valign="top">Account Approved Email:</td>
            	<td><textarea name="approved_message" style="width:500px;height:100px;"><?php echo $approved_message;?></textarea></td>
            </tr>
            <tr>
           		<td align="right" valign="top">Account Denied Email:</td>
            	<td><textarea name="denied_message" style="width:500px;height:100px;"><?php echo $denied_message;?></textarea></td>
            </tr>
            <tr>
            	<td></td>
                <td align="right">
                	<table width="100%">
                    <tr>
                    	<td>Short Code Key: [username]</td>
                        <td align="right"><input type="submit" name="reset_messages" value="Reset Messages" onclick="return confirm('Are you sure you want to reset to the default messages?');" /></td>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>
        </div>
        <script>
	   function show_messages(){
	   		if(document.getElementById('bp_moderate').checked == true){
	   			document.getElementById('bp_messages').style.display='';
  			}else{
				document.getElementById('bp_messages').style.display='none';
			}
		}
		<?php if($bp_moderate=="yes"){
			echo "document.getElementById('bp_messages').style.display='';";
		}?>
	   </script>
        <br />
       <!-- &nbsp;<input type="checkbox" name="bp_captcha" value="yes"  <?php if($bp_captcha=="yes"){?>checked<?php }?>/>&nbsp;<strong>Use Captcha</strong> (Stop spam bots from joining your website)<br />
        <br />-->
        Check groups or blogs members can join at registration:<br />
        <table>
        <tr>
        <td valign="top">
            <table>
            <tr>
                <td><strong>Groups:</strong></td>
            </tr>
            <?php //*fix wp_
            $sql = "SELECT id,name FROM ".$iprefix."bp_groups order by name";
            $rs = mysql_query($sql);
            if (mysql_num_rows($rs) > 0) {
                while ($r = mysql_fetch_assoc($rs)) {
                    $group_id = $r['id'];
                    $group_name=$r['name'];
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="bp_groups[]" value="<?php echo $group_id;?>"  <?php if(in_array($group_id, $bp_groups)){?>checked<?php }?>/>&nbsp;<?php echo $group_name;?>
                        </td>
                    </tr>
                    <?php
                }
            }
            ?>
            </table>
        </td>
        <td valign="top">
            <table>
            <tr>
                <td><strong>Blogs:</strong></td>
            </tr>
            <?php
            $sql = "SELECT blog_id,path FROM ".$iprefix."blogs order by path";
            $rs = mysql_query($sql);
            if (mysql_num_rows($rs) > 0) {
                while ($r = mysql_fetch_assoc($rs)) {
                    $blog_id = $r['blog_id'];
                    $sql = "SELECT option_value FROM ".$iprefix.$blog_id."_options where option_name='blogname'";
                    $rs2 = mysql_query($sql);
                    if (mysql_num_rows($rs2) > 0) {
                        while ($r2 = mysql_fetch_assoc($rs2)) {
                            $blog_name=$r2['option_value'];
                        }
                    }?>
                    <tr>
                         <td>
                            <input type="checkbox" name="bp_blogs[]" value="<?php echo $blog_id;?>"  <?php if(in_array($blog_id, $bp_blogs)){?>checked<?php }?>/>&nbsp;<?php echo $blog_name;?>
                        </td>
                    </tr>
                    <?php
                }
            } ?>
            </table>
        </td>
        </tr>
        </table>
        <br />
        <input type="submit" name="Save" value="Save Options" />
    <?php }else{
		///New members requests*********************************************************
		if ($members_count > 0) { ?>
            Please approve or deny the following new members:
            <SCRIPT LANGUAGE="JavaScript">
			function bpro_checkall(field){
				if(document.getElementById('bp_checkall').checked == true){
					checkAll(field)
				}else{
					uncheckAll(field)
				}
			}
			function checkAll(field)
			{
			for (i = 0; i < field.length; i++)
				field[i].checked = true ;
			}
			
			function uncheckAll(field)
			{
			for (i = 0; i < field.length; i++)
				field[i].checked = false ;
			}

			</script>
            <table cellpadding="3" cellspacing="3">
            <tr>
            	<td><input type="checkbox" id="bp_checkall" onclick="bpro_checkall(document.bpro.bp_member_check);" name="checkall" /></td>
                <td><strong>Photo</strong></td>
                <td><strong>Name</strong></td>
                <?php
				$groups="";
				foreach ($bp_groups as $value) {
    				$groups=$groups.",".$value;
				}
				if ($groups!=""){
					$groups="00".$groups;?>
                	<td><strong>Requested Private Groups</strong></td>
                <?php } ?>
            	<td><strong>Email</strong></td>
                <td><strong>Created</strong></td>
            </tr>
			<?php while ($r = mysql_fetch_assoc($rs)) {
				$user_id=$r['ID'];
				$userlink=bp_core_get_userurl($user_id);
				$username=bp_core_get_user_displayname($user_id, true );
				$userpic=bp_core_get_avatar( $user_id, 1 );
				$useremail=$r['user_email'];
				$userregistered=$r['user_registered'];
				if($bgc==""){
					$bgc="#ffffff";
				}else{
					$bgc="";
				}?>
				<tr style="background:<?php echo $bgc;?> !important;">
                    <td valign="top"><?php //echo $user_id; ?><input type="checkbox" id="bp_member_check" name="bp_member_check[]" value="<?php echo $user_id; ?>"  /></td>
                    <td valign="top"><a target="_blank" href="<?php echo $userlink; ?>"><?php echo $userpic?></a></td>
                    <td valign="top"><strong><?php echo $username?></strong><br /><a target="_blank" href="<?php echo $userlink; ?>">view profile</a></td>
                    <?php if ($groups!=""){ ?>
                    <td valign="top">
                    	<?php $sql="select a.id,a.name from ".$iprefix."bp_groups a, ".$iprefix."bp_groups_members b where a.id=b.group_id and b.user_id=$user_id and a.id in (".$groups.") and a.status in ('semi','private') and b.is_confirmed=0";
						$rs2 = mysql_query($sql);
						if (mysql_num_rows($rs2) > 0) {
							while ($r2 = mysql_fetch_assoc($rs2)) {
								$group_id = $r2['id'];
								$group_name = $r2['name'];
								?>
                    			[<input checked="checked" type="radio" name="usergroup_<?php echo $user_id; ?>_<?php echo $group_id; ?>" value="approve" />Approve<input type="radio" name="usergroup_<?php echo $user_id; ?>_<?php echo $group_id; ?>" value="deny" />Deny<input type="radio" name="usergroup_<?php echo $user_id; ?>_<?php echo $group_id; ?>" value="ban" />Ban] <strong><?php echo $group_name; ?></strong><br />
                    		<?php }
						}else{
							echo "No private groups requested.";
						}?>
                    </td>
                    <?php } ?>
                    <td valign="top"><a href="mailto:<?php echo $useremail;?>"><?php echo $useremail;?></a></td>
                    <td valign="top"><?php echo $userregistered;?></td>

                </tr>
            <?php } ?>
            </table>
            
			<br />
            <input type="submit" name="Moderate" value="Approve" />
            <input type="submit" name="Moderate" value="Deny" onclick="return confirm('Are you sure you want to deny and delete the checked memeber(s)?');" />
         <?php }else{
		 	echo "No new members to approve.";
		 }
    } ?>
    </form>
    <br />
    For support please visit the <a target="_blank" href="http://webdevstudios.com/support/forum/buddypress-registration-options/">BP-Registration-Options Plugin Support Forum</a> | Version by <a href="http://webdevstudios.com">WebDevStudios.com</a><br />
    <a target="_blank" href="http://webdevstudios.com/support/wordpress-plugins/">Check out our other plugins</a> and follow <a target="_blank" href="http://twitter.com/webdevstudios">@WebDevStudios</a> and <a target="_blank" href="http://twitter.com/bmess">@bmess</a> on Twitter
<?php }
//ACCOUNT ACTIVATION ACTIONS*******************************************************************
//ACCOUNT ACTIVATION ACTIONS*******************************************************************
//ACCOUNT ACTIVATION ACTIONS*******************************************************************
//ACCOUNT ACTIVATION ACTIONS*******************************************************************
//ACCOUNT ACTIVATION ACTIONS*******************************************************************
function bprwg_update_profile(){
	global $wpdb, $bp, $user_id;
	switch_to_blog(1);
	$blog_id=get_current_site()->id;
	$iprefix=$wpdb->prefix;
	$iprefix=str_replace("_".$blog_id,"",$iprefix);
	//Get Current User_ID
	//$userid = $bp->loggedin_user->id;
	//If not logged in they came from activation page
	if ($userid=="") {
		$key=$_GET['key'];
		if ($key!=""){
			if ( bp_account_was_activated() ) :
				$sql="select ID from ".$iprefix."users where user_activation_key='$key'";
				$rs = mysql_query($sql);
				if (mysql_num_rows($rs) > 0) {
					while ($r = mysql_fetch_assoc($rs)) {
						$userid=$r['ID'];
						$from_reg="yes";
					}
				}
			endif;
		}
	}
	//Can only pass if user_id exists
	if ($userid!="") {
		$user_info = get_userdata($userid);
		$username=$user_info->user_login;
		$useremail=$user_info->user_email;
		//BLOGS
		$bp_blogs=get_option('bprwg_blogs');
		if($bp_blogs!=""){
			$userblogs=get_option('bprwg_newmember_blogs_'.$useremail);
			if($userblogs!=""){
				$arr_userblogs = explode(",", $userblogs);
				for($i = 0; $i < count($arr_userblogs); $i++){
					$blog_id = $arr_userblogs[$i];
					$sql = "SELECT option_value FROM ".$iprefix.$blog_id."_options where option_name='default_role'";
					$rs = mysql_query($sql);
					if (mysql_num_rows($rs) > 0) {
						while ($r = mysql_fetch_assoc($rs)) {
							$default_role=$r['option_value'];
							add_user_to_blog($blog_id, $userid, $default_role);
						}
					}
				}
			}
			delete_option('bprwg_newmember_blogs_'.$useremail);
		}
		//GROUPS
		$bp_groups=get_option('bprwg_groups');
		if($bp_groups!=""){
			$usergroups=get_option('bprwg_newmember_groups_'.$useremail);
			$sql="select id,status,name,slug from ".$iprefix."bp_groups where id in ($usergroups) order by name";
			$rs = mysql_query($sql);
			if (mysql_num_rows($rs) > 0) {
				while ($r = mysql_fetch_assoc($rs)) {
					$id = $r['id'];
					$status = $r['status'];
					$name = $r['name'];
					$slug = $r['slug'];
					if($status=="semi" || $status=="private"){
						$is_confirmed=0;
					}else{
						$is_confirmed=1;
					}
					//if not already in group then add to group
					$sql="select id from ".$iprefix."bp_groups_members where group_id=$id and user_id=$userid";
					$rs2 = mysql_query($sql);
					if (mysql_num_rows($rs2) == 0) {
						//add memebr to group and send group confirmation if need be
						$sql="insert into ".$iprefix."bp_groups_members(group_id,user_id,inviter_id,user_title,date_modified,comments,is_confirmed)values($id,$userid,0,'',now(),'',$is_confirmed)";
						$wpdb->query($wpdb->prepare($sql));
						if ($is_confirmed==0){
							$group_email=$group_email.$username." wants to join the group [".$name."] - ".get_bloginfo("url")."/groups/".$slug."/admin/membership-requests.\n\n";
						}
					}
				}
			}
			delete_option('bprwg_newmember_groups_'.$useremail);
		}
		//for member moderation after member activation...
		if ($from_reg="yes"){
			$bp_moderate=get_option('bprwg_moderate');
			if ($bp_moderate=="yes"){
				//add/update usermeta status to activated
				update_usermeta($userid, 'bprwg_status', 'activated');
				//update wp_users to deleted=1, this will prevent member from being listed in member directory but not actually delete them. once appoved will be updated back to 0, if denied will fully delete account
				$sql="update ".$iprefix."users set deleted=1 where ID=$userid";
				$wpdb->query($wpdb->prepare($sql));
				//fire off email to admin about new memebr with links to accept or reject
				$mod_email=$username." would like to become a member of your website, to accept or reject their request please go to ".get_bloginfo("url")."/wp-admin/options-general.php?page=bp-registration-options/bp-registration-options.php&view=members \n\n";
			}
			//delete user_activation_key after activation
			$sql="update ".$iprefix."users set user_activation_key='' where ID=$userid";
			$wpdb->query($wpdb->prepare($sql));
		}
		//Send Emails for new member or request access to goups
		if($group_email!="" || $mod_email!=""){
			$the_email="";
			if($mod_email!=""){
				$the_email.=$mod_email;
			}
			if($mod_email!=""){
				$the_email.=$group_email;
			}
			$admin_email = get_option('admin_email');
			wp_mail($admin_email, 'New Member Request', $the_email);
		}
	}
}

//called from activation hook
function bprwg_activate($userid){
	global $wpdb, $bp;
	$blog_id=get_current_site()->id;
	$iprefix=$wpdb->prefix;
	$iprefix=str_replace("_".$blog_id,"",$iprefix);
	//get key from querystring and update user_activation_key in wp_users (has already been deleted on activation, put back in so we can grab it after bp activation stuff runs then delete it again)
	$key=$_GET['key'];
	$sql="update ".$iprefix."users set user_activation_key='$key' where ID=$userid";
	$wpdb->query($wpdb->prepare($sql));
}
add_action( 'wpmu_activate_user', 'bprwg_activate');
add_filter('bp_before_activate_content', 'bprwg_update_profile');


//MODERATION - New member alert message and redirect*****************************
function bprwg_approve_message(){
	global $wpdb;
	//check if moderation is on
	$bp_moderate=get_option('bprwg_moderate');
	if ($bp_moderate=="yes"){
		$showit="yes";
		if(strrpos($_SERVER['REQUEST_URI'],"/activate")!== false){
			$showit="no";
			global $bp;
			$userid =  $bp->loggedin_user->id ;
			if ( bp_account_was_activated() ) :
				$showit="yes";
				$hidelogout="yes";
			endif;
		}
		if($showit=="yes"){
			$activate_message=get_option('bprwg_activate_message');
			echo '<div id="message" class="error"><p>'.$activate_message.'<br>';
			if($hidelogout!="yes"){
				echo '<a href="'.wp_logout_url( get_bloginfo("url") ).'" title="Logout">Logout</a>';
			}
			echo '</p></div>';
		}
	}
}
function bprwg_redirect(){
	global $wpdb;
	//redirect from wp-signup.php
	if(strrpos($_SERVER['REQUEST_URI'],"/wp-signup.php")!== false ){
		$url=get_option('siteurl')."/register";
		wp_redirect($url, 301);
		//if for some reason wp_redirect isn't working.(maybe other plugins runing before with output)
		echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
		exit();
	}
	$bp_moderate=get_option('bprwg_moderate');
	//check if moderation is on
	if ($bp_moderate=="yes"){
		//only restrict buddypress pages
		if(strrpos($_SERVER['REQUEST_URI'],"/members")!== false || strrpos($_SERVER['REQUEST_URI'],"/groups")!== false){
			global $bp;
			//check if logged in
			$userid =  $bp->loggedin_user->id ;
			if ($userid!=""){
				$user_info = get_userdata($userid);
				$username=$user_info->user_login;
				$url="/members/".$username."/profile/";
				//check if approved or grandfathered in (already had account when pluhin activated)
				$status = get_usermeta($userid, 'bprwg_status');
				if($status!="approved"&& $status!=""){
					//check if allowed buddypress pages
					if($url==$_SERVER['REQUEST_URI'] || strrpos($_SERVER['REQUEST_URI'],$url."change-avatar")!== false || strrpos($_SERVER['REQUEST_URI'],$url."edit")!== false || strrpos($_SERVER['REQUEST_URI'],"wp-login.php?action=logout")!== false){
						add_filter('bp_before_profile_menu', 'bprwg_approve_message');
					}else{
						$url=get_option('siteurl').$url;
						wp_redirect($url);
						//if for some reason wp_redirect isn't working.(maybe other plugins runing before with output)
						echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
						exit();
					}
				}
			}
		}
	}
}
add_action('init', 'bprwg_redirect', -1);
add_filter('bp_after_activate_content', 'bprwg_approve_message');


//ADMIN DASHBOARD MESSAGE******************************************************
function bprwg_admin_msg() {
	global $wpdb;
	if (current_user_can('manage_options')){
		$bp_moderate=get_option('bprwg_moderate');
		if ($bp_moderate=="yes"){
			$blog_id=get_current_site()->id;
			$iprefix=$wpdb->prefix;
			$iprefix=str_replace("_".$blog_id,"",$iprefix);
			$sql="Select a.* from ".$iprefix."users a LEFT OUTER JOIN ".$iprefix."usermeta b on a.ID=b.user_id where b.meta_key='bprwg_status' and meta_value<>'approved' and meta_value<>'denied' order by a.ID";
			$rs = mysql_query($sql);
			$members_count=mysql_num_rows($rs);
			if($members_count>0){
				if($members_count!=1){
					$s="s";
				}
				echo '<div class="error"><p>You have <a href="'.get_bloginfo("url").'/wp-admin/options-general.php?page=bp-registration-options/bp-registration-options.php&view=members"><strong>'.$members_count.' new member request'.$s.'</strong></a> that you need to approve or deny. Please <a href="'.get_bloginfo("url").'/wp-admin/options-general.php?page=bp-registration-options/bp-registration-options.php&view=members">click here</a> to take action.</p></div>';
			}
		}
	}
}
function bprwg_admin_init() {
	add_action('admin_notices', 'bprwg_admin_msg');
}
add_action( 'admin_init', 'bprwg_admin_init' );

//REGISTRATION FORM*******************************************************************************
function bprwg_register_page(){
	global $wpdb, $bp, $user_id;
	switch_to_blog(1);
	$blog_id=get_current_site()->id;
	$iprefix=$wpdb->prefix;
	$iprefix=str_replace("_".$blog_id,"",$iprefix);
	//GROUPS
	$bp_groups=get_option('bprwg_groups');
	if($bp_groups!=""){
		$sql = "SELECT id,name FROM ".$iprefix."bp_groups where id in ($bp_groups) order by name";
		$rs = mysql_query($sql);
		if (mysql_num_rows($rs) > 0) { ?>
			<div id="bp_registration-options-groups">
            <strong>Group(s)</strong><br />
			<?php while ($r = mysql_fetch_assoc($rs)) {
				$group_id = $r['id'];
				$group_name=$r['name'];?>
                <input type="checkbox" name="bprwg_groups[]" value="<?php echo $group_id; ?>" />&nbsp;<?php echo $group_name; ?>&nbsp;&nbsp;
            <?php }
			echo "<br />Check one or more groups you would like to join.</div>";
		}
	}
	//BLOGS
	$bp_blogs=get_option('bprwg_blogs');
	if($bp_blogs!=""){ ?>
    	<div id="bp_registration-options-blogs">
        <strong>Blog(s)</strong><br />
		<?php $arr_bp_blogs = explode(",", $bp_blogs);
		for($i = 0; $i < count($arr_bp_blogs); $i++){
			$blog_id = $arr_bp_blogs[$i];
			$sql = "SELECT option_value FROM ".$iprefix.$blog_id."_options where option_name='blogname'";
			$rs = mysql_query($sql);
			if (mysql_num_rows($rs) > 0) {
				while ($r = mysql_fetch_assoc($rs)) {
					$blog_name=$r['option_value'];?>
					<input type="checkbox" name="bprwg_blogs[]" value="<?php echo $blog_id; ?>" />&nbsp;<?php echo $blog_name; ?>&nbsp;&nbsp;
				<?php }
			}
		}
		echo "<br />Check one or more blogs you would like to join.</div>";
	}
	//captcha
}
add_filter('bp_before_registration_submit_buttons', 'bprwg_register_page');

//REGISTRATION ACTIONS*************************************************************
function bprwg_register_save(){
	global $wpdb;
	switch_to_blog(1);
	$iemail=$_POST['signup_email'];
	//echo $iemail;
	$bp_groups=$_POST['bprwg_groups'];
	$bp_groups_str = implode(",", $bp_groups);
	update_option('bprwg_newmember_groups_'.$iemail, $bp_groups_str);
	$bp_blogs=$_POST['bprwg_blogs'];
	$bp_blogs_str = implode(",", $bp_blogs);
	update_option('bprwg_newmember_blogs_'.$iemail, $bp_blogs_str);
	//exit();
}
add_action( 'bp_complete_signup', 'bprwg_register_save' );
?>