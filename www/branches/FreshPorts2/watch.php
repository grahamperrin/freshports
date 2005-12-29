<?php
	#
	# $Id: watch.php,v 1.1.2.57 2005-12-29 12:37:56 dan Exp $
	#
	# Copyright (c) 1998-2005 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/databaselogin.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/getvalues.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/watch-lists.php');

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/ports_updating.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/watch_list.php');

	// if we don't know who they are, we'll make sure they login first
	if (!$visitor) {
		header("Location: /login.php?origin=" . $_SERVER["PHP_SELF"]);  /* Redirect browser to PHP web site */
		exit;  /* Make sure that code below does not get executed when we redirect. */
	}

	$Debug = 0;

	if ($Debug) phpinfo();

	$visitor = $_COOKIE['visitor'];

	$IncludeUpdating              = IsSet($_REQUEST['updating']);
	$OnlyThoseWithUpdatingEntries = IsSet($_REQUEST['updatingonly']);

	if ($_POST["watch_list_select_x"] && $_POST["watch_list_select_y"]) {
		# they clicked on the GO button and we have to apply the 
		# watch staging area against the watch list.
		$wlid = AddSlashes($_POST["wlid"]);
		if ($Debug) echo "setting SetLastWatchListChosen => \$wlid='$wlid'";
		$User->SetLastWatchListChosen($wlid);
		if ($Debug) echo "\$wlid='$wlid'";
	} else {
		$wlid = $User->last_watch_list_chosen;
		if ($Debug) echo "\$wlid='$wlid'";
		if ($wlid == '') {
			$WatchLists = new WatchLists($db);
			$wlid = $WatchLists->GetDefaultWatchListID($User->id);
			if ($wlid == '') {
				syslog(LOG_NOTICE, "www/watch.php::line 43 \$wlid='$wlid'");
			}
			if ($Debug) echo "GetDefaultWatchListID => \$wlid='$wlid'";
		}
	}

	$Title = "Watch List";
	if ($wlid != '') {
		$WatchList = new WatchList($db);
		$WatchList->Fetch($User->id, $wlid);

		$Title .= " - " . $WatchList->name;
	}

	freshports_Start($Title,
					'freshports - new ports, applications',
					'FreeBSD, index, applications, ports');

	$PortsUpdating   = new PortsUpdating($db);

?>

<?php echo freshports_MainTable(); ?>

<tr><td valign="top" width="100%">
<table width="100%" border="0">
<tr>
	<? echo freshports_PageBannerText($Title); ?>
</tr>
<tr><td valign="top">
<table border=0 width="100%">
<tr><td>
These are the ports which are on your <a href="watch-categories.php">watch list</A>. 
That link also occurs on the right hand side of this page, under Login.
</td><td valign="top" nowrap align="right">

<?php

if ($wlid != '') {
	echo freshports_WatchListDDLBForm($db, $User->id, $wlid);
}

?>

</td></tr>
</table>
</td></tr>
<script language="php">

// make sure the value for $sort is valid

echo "<tr><td>\nThis page is ";

$sort = $_GET["sort"];

switch ($sort) {
/* sorting by port is disabled. Doesn't make sense to do this
   case "port":
      $sort = "version, updated desc";
      $cache_file .= ".port";
      break;
*/
   case "category":
      $sort ="category, port";
      echo 'sorted by category.  but you can sort by <a href="' . $_SERVER["PHP_SELF"] . '?sort=updated">last update</a>';
      $ShowCategoryHeaders = 1;
      $cache_file .= ".updated";
      break;

   default:
      $sort = "commit_date_sort_field desc, port";
      echo 'sorted by last update date.  but you can sort by <a href="' . $_SERVER["PHP_SELF"] . '?sort=category">category</a>';
      $ShowCategoryHeaders = 0;
      break;

}

echo "</td></tr>\n";

?>

<tr><td>
<?php
	if ($OnlyThoseWithUpdatingEntries) {
		echo '<a href="?updating">View watched ports + entries from </code>/usr/ports/UPDATING</code></a>';
	} else {
		if ($IncludeUpdating) {
			echo '<a href="http://' .  $_SERVER['HTTP_HOST'] .  $_SERVER['PHP_SELF'] . '">View all watched ports</a>';
		} else {
			echo '<a href="?updating">View all watched ports + entries from </code>/usr/ports/UPDATING</code></a>';
		}
	}

	echo "\n<br>\n";

	if ($OnlyThoseWithUpdatingEntries) {
		echo '<a href="http://' .  $_SERVER['HTTP_HOST'] .  $_SERVER['PHP_SELF'] . '">View all watched ports.</a>';
	} else {
		echo '<a href="?updatingonly">View only watched ports with entries in </code>/usr/ports/UPDATING</code></a>';
	}

?>
</td></tr>

<?php


if ($wlid == '') {
	echo '<tr><td align="right">You have no watch lists.</td></tr>';
} else {
	
	$sql = "
	SELECT temp.*,
		to_char(max(commit_log.commit_date) - SystemTimeAdjust(), 'DD Mon YYYY HH24:MI:SS') 	as updated,
		commit_log.committer, commit_log.description 														as update_description, 
		commit_log.message_id, max(commit_log.commit_date) 												as commit_date_sort_field,
		commit_log.committer
	from commit_log
		RIGHT OUTER JOIN
	(
	
	select element.name 			as port, 
			 ports.id 				as id, 
	       categories.name 		as category, 
	       categories.id 		as category_id, 
	       ports.version 		as version, 
	       ports.revision 		as revision, 
	       element.id 			as element_id, 
	       ports.maintainer, 
	       ports.short_description, 
	       ports.last_commit_id, 
	       ports.package_exists, 
	       ports.extract_suffix, 
	       ports.homepage, 
		   to_char(ports.date_added - SystemTimeAdjust(), 'DD Mon YYYY HH24:MI:SS') 		as date_added, 
	       element.status, 
	       ports.broken, 
	       ports.deprecated, 
	       ports.ignore, 
	       ports.forbidden, 
           ports.master_port,
           ports.latest_link,
           ports.no_package,
           ports.package_name,
           ports.restricted,
           ports.no_cdrom,
           ports.expiration_date,
	       1 as onwatchlist 
	       from watch_list_element, element, categories, ports
	 WHERE ports.category_id                = categories.id 
	   and watch_list_element.element_id    = ports.element_id 
		and ports.element_id                 = element.id
	   and watch_list_element.watch_list_id = $wlid
	
	
	) as TEMP
	on (TEMP.last_commit_id = commit_log.id) 
	
	GROUP BY temp.port,
			 temp.id, 
	         temp.category, 
	         temp.category_id, 
	         temp.version, 
	         temp.revision, 
	         commit_log.committer, 
	         update_description, 
	         temp.element_id, 
	         temp.maintainer, 
	         temp.short_description, 
	         temp.date_added, 
	         temp.last_commit_id, 
	         commit_log.message_id, 
	         temp.package_exists, 
	         temp.extract_suffix, 
	         temp.homepage, 
	         temp.status, 
	         temp.broken, 
	         temp.deprecated, 
	         temp.ignore, 
	         temp.forbidden, 
             temp.master_port,
             temp.latest_link,
             temp.no_package,
             temp.package_name,
             temp.restricted,
             temp.no_cdrom,
             temp.expiration_date,
	         temp.onwatchlist  
	";
	
	$sql .= " order by $sort ";
	
	if ($Debug) {
	   echo "<pre>$sql</pre>";
	}
	
	$result = pg_exec($db, $sql);
	if (!$result) {
		echo pg_errormessage();
	}

	// get the list of topics, which we need to modify the order
	$NumPorts=0;

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/ports.php');
	$port = new Port($db);
	$port->LocalResult = $result;

	$LastCategory='';
	$GlobalHideLastChange = 'N';
	$numrows = pg_numrows($result);

	$TextNumRowsFound = '<p><small>';
	if ($numrows > 1) {
		$TextNumRowsFound .= "$numrows ports";
	} else {
		if ($numrows == 1) {
			$TextNumRowsFound .= 'one port';
		} else {
			$TextNumRowsFound .= 'no ports';
		}
	}
	$TextNumRowsFound .= ' found';


	$TextNumRowsFound .= '</small>';
	echo "</p>\n<tr><td>";

	if ($numrows > 0) {
		// Display the first row count only if there is a port.
		echo $TextNumRowsFound;

		if ($OnlyThoseWithUpdatingEntries) {
			echo '<small> on your watch list (but only showing those with entries in <code>/usr/ports/UPDATING</code>)</small>';
		}
	}

	$ShowDescriptionLink = 0;
	$DaysMarkedAsNew= $DaysMarkedAsNew= $GlobalHideLastChange= $ShowChangesLink = $ShowDownloadPortLink= $ShowHomepageLink= $ShowLastChange= $ShowMaintainedBy= $ShowPortCreationDate= $ShowPackageLink= $ShowShortDescription =1;
	$ShowPortCreationDate = 0;
	$HideCategory = 1;
	$ShowCategories		= 1;
	GLOBAL	$ShowDepends;
	$ShowDepends		= 1;
	$HideDescription = 1;
	$ShowEverything  = 1;
	$ShowShortDescription = 'Y';
	$ShowMaintainedBy     = 'Y';
	#$GlobalHideLastChange = 'Y';
	$ShowDescriptionLink  = 'N';

	if ($ShowCategoryHeaders) {
		echo '<DL>';
	}


	$NumSkipped = 0;
	for ($i = 0; $i < $numrows; $i++) {
		$port->FetchNth($i);
		$NumRowsUpdating = $PortsUpdating->FetchInitialise($port->id);
		if ($OnlyThoseWithUpdatingEntries && $NumRowsUpdating == 0){	
			$NumSkipped++;
			continue;
		}

		if ($ShowCategoryHeaders) {
			$Category = $port->category;
	
			if ($LastCategory != $Category) {
				if ($i > 0) {
					echo "\n</DD>\n";
				}

				$LastCategory = $Category;
				if ($ShowCategoryHeaders) {
					echo '<DT>';
				}

				echo '<BIG><BIG><B><a href="/' . $Category . '/">' . $Category . '</a></B></BIG></BIG>';
				if ($ShowCategoryHeaders) {
					echo "</DT>\n<DD>";
				}
			}
		}

		echo freshports_PortDetails($port, $db, $DaysMarkedAsNew, $DaysMarkedAsNew, $GlobalHideLastChange, $HideCategory, $HideDescription, $ShowChangesLink, $ShowDescriptionLink, $ShowDownloadPortLink, $ShowEverything, $ShowHomepageLink, $ShowLastChange, $ShowMaintainedBy, $ShowPortCreationDate, $ShowPackageLink, $ShowShortDescription, 1, '', 0);

		if ($IncludeUpdating || $OnlyThoseWithUpdatingEntries) {
			freshports_UpdatingOutput($NumRowsUpdating, $PortsUpdating, $port);
		}
		
		echo '<BR>';
	}

	if ($ShowCategoryHeaders) {
		echo "\n</DD>\n</DL>\n";
	}

	echo "</td></tr>\n";

	echo "<tr><td>$TextNumRowsFound";

	if ($OnlyThoseWithUpdatingEntries) {
		echo '<small> on your watch list (but only showing ' . ($numrows - $NumSkipped) . ')</small>';
	}

	echo "</p>\n</td></tr>\n";

} // end if no wlid

</script>
</table>
</td>

  <TD VALIGN="top" WIDTH="*" ALIGN="center">
	<?
	echo freshports_SideBar();
	?>
  </td>

</tr>
</table>

<?
echo freshports_ShowFooter();
?>

</body>
</html>
