<?php
/**
 * This file lists unapproved entries for one or more users.
 *
 * Optional parameters in URL:
 * url=user specifies that we should only display unapproved
 *   events for that one user
 *
 * The user will be allowed to approve/reject the event if:
 * it is on their own calendar
 *
 * @author Craig Knudsen <cknudsen@cknudsen.com>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @package WebCalendar
 * @version $Id$
 */
include_once 'includes/init.php';
send_no_cache_header ();

if ( empty ( $user ) )
  $user = $login;

// Only admin user or assistant can specify a username other than his own.
if ( ! $is_admin && $user != $login  && ! $is_assistant &&
  ! access_is_enabled () )
  $user = $login;

$HeadX = '';
if ( $AUTO_REFRESH == "Y" && ! empty ( $AUTO_REFRESH_TIME ) ) {
  $refresh = $AUTO_REFRESH_TIME * 60; // convert to seconds
  $returl = "list_unapproved.php";
  if ( ! empty ( $user ) && $user != $login )
    $returl .= "?user=" . $user;
  $HeadX = "<meta http-equiv=\"refresh\" content=\"$refresh; URL=" .
    $returl . "\" />\n";
}
$INC = array('js/popups.php');
print_header($INC,$HeadX);

$key = 0;

if ( ! empty ( $user ) && $user != $login ) {
  $retarg = 'list';
} else {
  $retarg = 'listall';
}

// List all unapproved events for the specified user.
// Exclude "extension" events (used when an event goes past midnight)
// TODO: only include delete link if they have permission to delete
// when user access control is enabled.
function list_unapproved ( $user ) {
  global $temp_fullname, $key, $login, $retarg, $NONUSER_ENABLED;
  $count = 0;
  
  user_load_variables ( $user, "temp_" );
  //echo "Listing events for $user<br />";

  $sql = "SELECT webcal_entry.cal_id, webcal_entry.cal_name, " .
    "webcal_entry.cal_description, webcal_entry_user.cal_login, " .
    "webcal_entry.cal_priority, webcal_entry.cal_date, " .
    "webcal_entry.cal_time, webcal_entry.cal_duration, " .
    "webcal_entry_user.cal_status, webcal_entry.cal_type " .
    "FROM webcal_entry, webcal_entry_user " .
    "WHERE webcal_entry.cal_id = webcal_entry_user.cal_id " .
    "AND ( webcal_entry.cal_ext_for_id IS NULL " .
    "OR webcal_entry.cal_ext_for_id = 0 ) AND " .
    "( webcal_entry_user.cal_login = ?  ";
      
  $sql .= ") AND webcal_entry_user.cal_status = 'W' " .
    "ORDER BY webcal_entry_user.cal_login, webcal_entry.cal_date";
  $res = dbi_execute ( $sql , array ( $user ) );
  $eventinfo = "";
  if ( $res ) {
    while ( $row = dbi_fetch_row ( $res ) ) {
      $key++;
      $id = $row[0];
      $name = $row[1];
      $description = $row[2];
      $cal_user = $row[3];
      $pri = $row[4];
      $date = $row[5];
      $time = $row[6];
      $duration = $row[7];
      $status = $row[8];
      $type = $row[9];
      $view_link = 'view_entry';      

      if ($count == 0 ) { 
        echo "<h3>" . $temp_fullname . "</h3>\n";      
        echo "<ul>\n"; 
      }
      
      $divname = "eventinfo-pop$id-$key";
      $linkid  = "pop$id-$key";
      echo "<li><a  title=\"" . translate("View this entry") .
        "\" class=\"entry\" id=\"$linkid\" href=\"$view_link.php?id=$id&amp;user=$cal_user\">";
      $timestr = "";
      if ( $time > 0 ) {
			  $eventstart = date_to_epoch ( $date . $time );
				$eventstop = $eventstart + $duration;
        $timestr = display_time ('', 0, $eventstart);
        if ( $duration > 0 ) {
          $timestr .= " - " . display_time ( '', 0 , $eventstop );
        }
      }
      echo htmlspecialchars ( $name );
      echo "</a>";
      echo " (" . date_to_str ($date) . ")\n";
      //approve
      echo ": <a title=\"" . 
        translate("Approve/Confirm") . 
     "\"  href=\"approve_entry.php?id=$id&amp;ret=$retarg&amp;user=$cal_user&amp;type=$type";
      if ( $user == "__public__" )
        echo "&amp;public=1";
      echo "\" class=\"nav\" onclick=\"return confirm('" .
        translate("Approve this entry?", true) . "');\">" . 
          translate("Approve/Confirm") . "</a>, ";
      //reject
      echo "<a title=\"" . 
        translate("Reject") . 
        "\" href=\"reject_entry.php?id=$id&amp;ret=$retarg&amp;user=$cal_user&amp;type=$type";
      if ( $user == "__public__" )
        echo "&amp;public=1";
      echo "\" class=\"nav\" onclick=\"return confirm('" .
        translate("Reject this entry?", true) . "');\">" . 
          translate("Reject") . "</a>";
      //delete
      if ( ! access_is_enabled () ||
        access_user_calendar ( 'edit', $user ) ) {
        echo ", <a title=\"" . 
          translate("Delete") . "\" href=\"del_entry.php?id=$id&amp;ret=$retarg";
        if ( $cal_user != $login )
          echo "&amp;user=$cal_user";
        echo "\" class=\"nav\" onclick=\"return confirm('" .
          translate("Are you sure you want to delete this entry?", true) . "');\">" . 
        translate("Delete") . "</a>";
      }
      echo "\n</li>\n";
      $eventinfo .= build_entry_popup ( $divname, $cal_user, $description,
        $timestr, site_extras_for_popup ( $id ));
      $count++;
    }
    dbi_free_result ( $res );
    if ($count > 0 ) { echo "</ul>\n"; }
  }
  if ( $count == 0  ) {
    echo "<p class=\"nounapproved\">" . 
      translate("No unapproved entries for") . "&nbsp;" . $temp_fullname . ".</p>\n";
  } else {
    if ( ! empty ( $eventinfo ) ) echo $eventinfo;
  }
}
?>

<h2><?php 
 etranslate("Unapproved Entries"); 
 //if ( $user == '__public__' ) echo " - " . $PUBLIC_ACCESS_FULLNAME; 
?></h2>
<?php
$app_users = array ();
$app_user_hash = array ( );


// If a user is specified, we list just that user.
if ( ( $is_assistant || $is_nonuser_admin || $is_admin ||
  access_is_enabled () ) &&
  ! empty ( $user ) && $user != $login ) {
  if ( ! access_is_enabled () || 
    access_user_calendar ( 'approve', $user ) ) {
    $app_users[] = $user;
    $app_user_hash[$user] = 1;
  } else {
    // not authorized to approve for specified user
    echo translate ( "Not authorized" );
  }
} else {
  // First, we list ourself
  $app_users[] = $login;
  $app_user_hash[$login] = 1;
  if ( access_is_enabled () ) {
    if ( $NONUSER_ENABLED == 'Y' ) {
      $all = array_merge ( get_my_users ( ), get_nonuser_cals ( ) );
    } else {
      $all = get_my_users ( );
    }
    for ( $j = 0; $j < count ( $all ); $j++ ) {
      $x = $all[$j]['cal_login'];
      if ( access_user_calendar ( 'approve', $x ) ) {
        if ( empty ( $app_user_hash[$x] ) ) {
          $app_users[] = $x;
          $app_user_hash[$x] = 1;
        }
      }
    }
  } else {
    if ( $is_admin && $PUBLIC_ACCESS == "Y" &&
      ( empty ( $user ) || $user != '__public__' ) ) {
      $app_users[] = '__public__';
      $app_users_hash['__public__'] = 1;
    }
    $all = get_nonuser_cals ( );
    for ( $j = 0; $j < count ( $all ); $j++ ) {
      $x = $all[$j]['cal_login'];
        if ( empty ( $app_user_hash[$x] ) ) {
          $app_users[] = $x;
          $app_user_hash[$x] = 1;
        }
    }
  }
}


for ( $i = 0; $i < count ( $app_users ); $i++ ) {
  // List unapproved entries for this user.
  list_unapproved ( $app_users[$i] );
}

?>

<?php print_trailer(); ?>
</body>
</html>
