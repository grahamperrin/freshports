<?php
	#
	# $Id: customize.php,v 1.3 2008-08-06 13:36:16 dan Exp $
	#
	# Copyright (c) 1998-2004 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/constants.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/databaselogin.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/htmlify.php');
	
	if (IN_MAINTENANCE_MODE) {
                header('Location: /' . MAINTENANCE_PAGE, TRUE, 307);
	}

	GLOBAL $User;

	$errors          = 0;
	$AccountModified = 0;
	$deleted         = 0;

$submit = $_REQUEST['submit'] ?? '';
$visitor = pg_escape_string($db, $_COOKIE[USER_COOKIE_NAME]);

// if we don't know who they are, we'll make sure they login first
if (!$visitor) {
	header('Location: /login.php');  /* Redirect browser to PHP web site */
	exit;  /* Make sure that code below does not get executed when we redirect. */
}

if ($submit) {
    #phpinfo();
    $Debug = 0;

    // process form
    syslog(LOG_ERR, 'into '. __FILE__);

	$confirmation = $_POST['confirmation'] ?? '';

	if ($confirmation ==  $User->name) {
		$result = pg_exec($db, "BEGIN");
        
		// Delete from the user table. The database will take care of the rest
		$sql = "SELECT DeleteUser($1)";
		$result = pg_query_params($db, $sql, array($User->id));
		if ($result) {
			$numrows = pg_affected_rows($result);
			if ($numrows == 1) {
				pg_exec($db, "COMMIT");
				# clear the cookie so they don't get "Your user details were not found. You have been logged out. Please return to the home page."
				freshports_CookieClear();
				$deleted = 1;
			} else {
				pg_exec($db, "ROLLBACK");
				$errors = 'I really tried to delete your account. I failed. Sorry.';
				syslog(LOG_ERR, 'attempted to delete user failed ' . $User->name . ' failed when trying to delete ' . $numrows . ' rows.');
            }
        } else {
            pg_exec($db, "ROLLBACK");

            syslog(LOG_ERR, 'attempt to delete user failed ' . $User->name . ' failed with error ' . pg_last_error($db));
            $errors = 'I could not delete that account. Sorry.';
        }
	} else {
        syslog(LOG_ERR, 'confirmation did not match: "' . $confirmation . '" != "' . $User->name . '"');
        $errors = 'The confirmation was not correct.';
    }
}

#	echo '<br>the page size is ' . $page_size . ' : ' . $email;

	freshports_Start('Delete User Account',
						'freshports - new ports, applications',
						'FreeBSD, index, applications, ports');
?>

<table class="fullwidth borderless" ALIGN="center">
<tr><td class="content">
<table class="fullwidth borderless">
  <tr>
    <td height="20"><?php


if ($errors) {
echo '<table class="fullwidth borderless">
<tr>
<td>
<table class="fullwidth borderless">
<tr class="accent"><td><b>Delete Failed!</b></td>
</tr>
<tr>
<td>
  <table class="fullwidth borderless" CELLPADDING="3">
  <tr VALIGN=top>
   <td><img src="/images/warning.gif"></td>
   <td width="100%">
  <p>The deleted failed.</p>';

echo $errors;

echo '<p>If you need help, please email postmaster@. </p>
 </td>
 </tr>
 </table>
</td>
</tr>
</table>
</td>
</tr>
</table>
<br>';
}  // if ($errors)

echo '<table class="fullwidth borderless">
<tr>
<td VALIGN="top">
<table class="fullwidth borderless">
<tr>
<td class="accent"><BIG>Customize</BIG></td>
</tr>
<tr>
<td>';


if ($deleted) {
    ?>
    <br><h2>Your account has been deleted.</h2>
    <p>Please click <a href="/">here</a> to return to the home page.</p>
    <?php
} else {
echo '<p>To delete your account, please enter you account name and click on <i>delete account</i>.</p><br>';

require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');

?>
<form action="<?php echo $_SERVER["PHP_SELF"] ?>" method="POST" NAME=f>
<table width="*" class="borderless">
          <tr>
            <td VALIGN="top">
               <p>The account name is: <?php echo $User->name; ?><p>
               User Name: <INPUT SIZE="15" NAME="confirmation" VALUE="">
            </td>
        </tr>
        <tr>
            <td>
                <br>
                <h2>There is no undo for this</h2>
                <INPUT TYPE="submit" VALUE="Delete Account" NAME="submit">
            </td>
        </tr>
  
    </table>
</FORM>

<?php
} // if ($deleted)
echo "</td>
</tr>
</table>
</td>
</tr>
</table>";

?>
</td>
</table>
</td>

  <td class="sidebar">
	<?php
	echo freshports_SideBar();
	?>
  </td>

</tr>
</table>

<?php
echo freshports_ShowFooter();
?>

</body>
</html>
