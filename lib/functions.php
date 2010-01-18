<?php
/**
 * Parse the templates directory and populate the global $Templates array with the files
 * 	Works recursively, to reference a deep template use a +key_path+ like you do with the
 *  dictionary.
 *    snippets.snippet5 =>  templates/snippets/snippet5 
 */
function load_templates($path = TEMPLATES_ROOT)
{
	$templates = array();
	$dh = opendir($path);
	while(($file = readdir($dh)) !== false)
	{
		if(preg_match('/^\.+/', $file)) continue;
		$_file_path = realpath($path.'/'.$file);
		$templates[$file] = is_dir($_file_path) ? load_templates($_file_path) : $_file_path;
	}
	closedir($dh);
	return $templates;
}

/**
 * converts the key path into an array path and check the $collection for an object
 */
function object_for_key_path($key_path, $collection = array())
{
  eval("\$_ = \$collection".key_path_to_array_path($key_path).";");
  if(!$_)
    throw new Exception("No object exist at key path: {$key_path}.");
  return $_;
}

/**
 * Grabs arrays from the dictionary and allows for complex key paths:
 * 	aops.personal_injury.verbs => $dictionary['aops']['personal_injury']['verbs']
 */ 
function entry_for_key_path($key_path)
{
	global $Dictionary;
	return object_for_key_path($key_path, $Dictionary);	
}

/**
 * Template file for key path
 */
function template_for_key_path($key_path) 
{
  global $Templates;
  return object_for_key_path($key_path, $Templates);
}

/**
 * Converts a key path into something we can run on an array so we can do dynamic pathong
 * on template and dictionary keys
 *  aops.personal_injury.verbs => ['aops']['personal_injury']['verbs']
 *  verbs => ['verbs']
 *  snippets.snippet1 => ['snippets']['snippet1']
 */
function key_path_to_array_path($key_path)
{
	if(strstr($key_path, '.'))
	{
		// split and convert word1.word2 into [word1][word2]
		$paths = preg_split('/\./', $key_path);
		return implode(array_map(create_function('$_', 'return "[\'$_\']";'), $paths));
	}else
    return '[\''.$key_path.'\']';
}

/**
 * Just a simple wrapper for +array_rand+ so we can keep our eval statement in +render_template+ simple
 * If we have an array of items we will randomize, otherwise we will just pass the specific entry on down the line
 */
function random_entry_for_key($key_path)
{
	$_ = entry_for_key_path($key_path);
	if(is_array($_))
		return $_[array_rand($_)];
	else
		return $_;
}

/**
 * Use Google's ajax translation API to do some rough translation magic. Life is easier with JSON parsing, but... substr works for this
 * If we don't get a 200/success from Google we return the original text back.
 */
function translate($text, $from, $to)
{
	$api_path = 'http://ajax.googleapis.com/ajax/services/language/translate?v=1.0&q=%s&langpair=%s|%s';
	$result = @file_get_contents(sprintf($api_path, urlencode($text), $from, $to));

	if(strstr($result, '200') && strstr($result, 'translatedText'))
	{
		/*
			if we are successful, rip the damn translation out.
			This comes back from Google in JSON format, but rather than go nuts parsing it, we can rip our translation by searching for
			the 'translatedText' block and using good ole substr.
				Result from google looks like (hopefully):
					{"responseData": {"translatedText":"[WE WANT THIS]"}, "responseDetails": null, "responseStatus": 200}
		*/
		$_start = strpos($result,'translatedText') + strlen('translatedText') + 3;
		$_end = strpos($result,'"},');
		$text = substr($result, $_start, ($_end - $_start));
	}
	// If translation fails, just return the original text. 
	return $text;
}

/**
 * Reads the template into a string and evaulates code when a +{...}+ statement is found using the key path found inside
 * 	{verbs} will randomly pick an entry from $Dictionary['verbs']
 */
function render_template($key_path, $scope = null)
{
	// if we are stupid and attempt to include ourself within ourself (infinite loop) we abort prematurely.
	if($scope == $key_path)
		return '';
		
	try{
		$template = file_get_contents(template_for_key_path($key_path));
    // any {!FLAG} render flag tag is stripped and stored in +RenderFlags+
    $template = preg_replace_callback('/\{!(.+)\}/iU', 'add_render_flags', $template);
		// any {<TEMPLATE} or {incl:TEMPLATE} tag is another template to be rendered here
		$template = preg_replace('/\{(?:<|incl:)(.+)\}/eiU', 'render_template("$1", $key_path)', $template);
		// any {trans:key_path,from,to} tags should be replaced
		$template = preg_replace('/\{trans:([^,]+),([^,]+),([^,]+)\}/eiU', 'translate(entry_for_key_path("$1"), "$2", "$3")', $template);
		// DO THIS LAST: any {key_path} should be replaced
		$template = preg_replace('/\{([^\s#!][\w\-\.]+)\}/eiU', 'random_entry_for_key("$1")', $template);		
		return $template;
	}catch(Exception $e){
		// TODO: handle or log this error -- bubble it up?
		echo "\n!!\tSomething went horribly wrong: {$e->getMessage()}\n\n";
	}
}

/**
 * Add flags to the template for after-processing if necessary. This can serve to keep track of certain things that were
 *  included and any other metadata you wish to keep. 
 * !! Should not be called directly since the first element is shifted off...
 */
function add_render_flags($matches = array())
{
  global $RenderFlags;
  array_shift($matches); // kill the first element of the match since its the entire string
  $_ = array();
  foreach($matches as $match)
  {
    $_ = array_merge($_, (strstr($match, ',') ? preg_split('/,/', $match) : array($match)));
  }
  $RenderFlags = array_unique(array_merge($RenderFlags, $_));
  return '';  
}

/**
 * Tries to get the render flag
 */
function get_render_flag($flag)
{
  global $RenderFlags;
  $_ = null;
  if($key = array_search($flag, $RenderFlags))
    $_ = $RenderFlags[$key];
  return $_;
}

/**
 * Wipe the render flags out
 */
function clear_render_flags()
{
  global $RenderFlags;
  $RenderFlags = array();
}

?>