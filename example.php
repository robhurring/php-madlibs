<?php

// The only file you need to include is +environment+
require_once('config/environment.php');

// Render test. If this passes, we are good to go :)
$_ = render_template('test-basic');
print_r($_);

// Render Flags list
echo "\nRenderFlags:\n";
print_r($RenderFlags);

// RenderFlags can be a simple way for a shared-template to communicate to the top-level app.
//  Example:
//    A template can include other templates. If one template is auto-generated and may not always 
//    be the same, you can flag that as {!dynamic} which can tell the main app to include a +disclaimer+
//    snippet to the bottom of it.
// 
if(get_render_flag('needs_disclaimer'))
{
  echo "\nKey Found: NEEDS DISCLAIMER\n";
  echo render_template('snippets.disclaimer')."\n";
}
  
echo ''
?>