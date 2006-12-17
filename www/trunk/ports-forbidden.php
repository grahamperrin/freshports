<?php
	#
	# $Id: ports-forbidden.php,v 1.2 2006-12-17 12:06:15 dan Exp $
	#
	# Copyright (c) 1998-2005 DVL Software Limited
	#

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/databaselogin.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/getvalues.php');

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/freshports_page_list_ports.php');

	$page = new freshports_page_list_ports();

	$page->setDB($db);

	$page->setTitle('Forbidden ports');
	$page->setDescription('These are the forbidden ports');

	$page->setSQL("ports.forbidden <> ''", $User->id);

	$page->display();
?>