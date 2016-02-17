<?php
	global $sourcedir;

	$file = $sourcedir . '/SimpleSEF.php';

	// A check to prevent errors on certain server configurations.
	if (!file_exists($file))
		return false;

	require_once($file);
	$simpleSEF = new SimpleSEF();
	$simpleSEF->fixHooks(true);
	unset($simpleSEF);
