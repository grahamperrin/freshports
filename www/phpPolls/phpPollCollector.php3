<?php
//////////////////////////////////////////////////////////////////////
//
// phpPolls - A voting booth for PHP3
//
// This file is "phpPollCollector.php3" and is responsible for
// collecting the user's votes. It is called from a form
// generated by phpPollUI.php3.
//
// This module sets a cookie preventing a user to vote twice.
//
// See phpPollConfig.php3 for configuration options.
//
// Copyright (c) 1999 Till Gerken (tig@skv.org)
//
// This software is released under the GNU Public License.
// Please see the accompanying file gpl.txt for licensing details!
//
//////////////////////////////////////////////////////////////////////


require "phpPollConfig.php3";


//////////////////////////////////////////////////////////////////////
//
// There are no functions in this module, everything is handled
// by the main program part.
//
// Global variables are taken over by the previously submitted form,
// according to their contents the database is being updated.
//
//////////////////////////////////////////////////////////////////////
//
// Calls to:
//	none
//
//////////////////////////////////////////////////////////////////////
//
// Global references:
//	$poll_mySQL_host, $poll_mySQL_user, $poll_mySQL_pwd
//	$poll_dbName, $poll_dataTableName, $poll_maxOptions
//	$poll_setCookies, $poll_usePersistentConnects
//		(from phpPollConfig.php3)
//	$poll_id, $poll_voteNr, $poll_forwarder
//		(from submitted form)
//
//////////////////////////////////////////////////////////////////////
//
// Author: tig
// Last change: 99/06/02
//
//////////////////////////////////////////////////////////////////////


// connect to database
if($poll_usePersistentConnects == 0)
	$poll_mySQL_ID = mysql_connect($poll_mySQL_host, $poll_mySQL_user, $poll_mySQL_pwd);
else
	$poll_mySQL_ID = mysql_pconnect($poll_mySQL_host, $poll_mySQL_user, $poll_mySQL_pwd);

// assume this vote is valid
$poll_voteValid = 1;

// first of all, check the IP locktable - in case the IP is in here,
// then invalidate vote
if($poll_IPLocking == 1)
{
	// we have to check for locked IPs, first of all clear all IPs
	// that are already timed out
	$poll_result = mysql_db_query($poll_dbName, "SELECT * FROM $poll_IPTableName");
	
	if(!$poll_result)
	{
		echo mysql_errno(). ": ".mysql_error(). "<br>";
		exit();
	}

	$current_time = time();

	while($poll_object = mysql_fetch_object($poll_result))
	{
		// did this IP time out?
		if(($poll_object->timeStamp + $poll_IPLockTimeout) < $current_time)
			// it did time out, delete it from the table
			@mysql_db_query($poll_dbName, "DELETE FROM $poll_IPTableName WHERE timeStamp=$poll_object->timeStamp");
	}

	// now we're done deleting old IPs, so check if this IP is already in the locktable
	$poll_result = mysql_db_query($poll_dbName, "SELECT * FROM $poll_IPTableName WHERE (votersIP='$REMOTE_ADDR') AND (pollID=$poll_id)");
	
	$poll_object = mysql_fetch_object($poll_result);
	
	if(!$poll_object)
		// the IP is not yet in the locktable, so insert it
		@mysql_db_query($poll_dbName, "INSERT INTO $poll_IPTableName (pollID, voteID, votersIP, timeStamp) VALUES ($poll_id, $poll_voteNr, '$REMOTE_ADDR', $current_time)");
	else	
		// the IP is already in the table => the vote must be invalid
		$poll_voteValid = 0;
}

// now check for cookies
if($poll_setCookies == 1)
{
	// we have to check for cookies, so get timestamp of this poll
	$poll_result = mysql_db_query($poll_dbName, "SELECT timeStamp FROM $poll_descTableName WHERE pollID=$poll_id");

	if(!$poll_result)
	{
		echo mysql_errno(). ": ".mysql_error(). "<br>";
		exit();
	}

	$poll_object = mysql_fetch_object($poll_result);
	$poll_timeStamp = $poll_object->timeStamp;

	$poll_cookieName = $poll_cookiePrefix.$poll_timeStamp;

	// check if cookie exists
	if($$poll_cookieName == "1")
		// cookie exists, invalidate this vote
		$poll_voteValid = 0;
	else
		// cookie does not exist yet, set one now
		setCookie("$poll_cookieName", "1");

}

// update database if the vote is valid
if($poll_voteValid == 1)
{
	$poll_result = mysql_db_query($poll_dbName, "UPDATE $poll_dataTableName SET optionCount=optionCount+1 WHERE (pollID=$poll_id) AND (voteID=$poll_voteNr)");
	if(!$poll_result)
	{
		echo mysql_errno(). ": ".mysql_error(). "<br>";
		exit();
	}

	// log the vote in the logging table
	if($poll_logging == 1)
	{
		$current_time = time();
		
		$poll_result = mysql_db_query($poll_dbName, "INSERT INTO $poll_logTableName (pollID, voteID, votersIP, timeStamp) VALUES ($poll_id, $poll_voteNr, '$REMOTE_ADDR', $current_time)");
		
		if(!$poll_result)
		{
			echo mysql_errno(). ": ".mysql_error(). "<br>";
			exit();
		}
	}

}
else
	if($poll_warnCheaters == 1)
	{
		// this vote is invalid, issue an error message
		echo "<html><body>";
		echo "You have already voted, your vote is being ignored. Please <a href=\"".$poll_forwarder."\">click here</a> to continue.";
		echo "</body></html>";
	}

// send header before outputting anything else, and only in case no warning has to be issued
if(($poll_voteValid == 1) || ($poll_warnCheaters == 0))
	Header("Location: $poll_forwarder");

// a lot of browsers can't handle it if there's an empty page
echo "<html><body></body></html>";

// close link to database
if($poll_usePersistentConnects == 0)
	mysql_close($poll_mySQL_ID);

//////////////////////////////////////////////////////////////////////

?>
