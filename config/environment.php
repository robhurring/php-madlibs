<?php

// Basic paths for the rest of the program to use
define('ROOT', realpath(dirname(__FILE__).'/../'));
define('DICTIONARY', ROOT.'/config/dictionary.yml');
define('TEMPLATES_ROOT', ROOT.'/templates');

// Common includes
require_once(ROOT.'/lib/spyc.php');
require_once(ROOT.'/lib/functions.php');

// Globals -- mind the Caps
$Dictionary = spyc_load_file(DICTIONARY);
$Templates = load_templates();
$RenderFlags = array();

?>