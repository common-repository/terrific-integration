<?php
/*
Plugin Name: Terrific Integration
Plugin URL: http://
Version: 1.1
Author: Leo Zurbriggen
Author URL: http://leoz.ch
Description: Integrates the terrific concept into wordpress by providing automatic concatenation of module styles and scripts and caching as well as additional features to simplify the working process.
*/

class Terrific
{
	public static function basePath() {
		return get_template_directory() . '/';
	}

	public static function isEnabled(){
		$modDir = Terrific::basePath().'modules';
		$cacheDir = Terrific::basePath().'cache';
		$assetDir = Terrific::basePath().'assets';

		return (is_dir($modDir) && is_dir($cacheDir) && is_dir($assetDir));
	}
	
	/**
	 * Check if any terrific actions are called and execute them
	 */
    public static function hook() {		
		if (isset($_GET["tc_flush"])) {
			if (current_user_can( 'manage_options' )) {
				Terrific::flushCache();
				header('Location: ' . $_SERVER['HTTP_REFERER']);
				echo "Cache flushed.";
			}else{
				echo "Permission denied.";
			}
			exit();
		}
    }

    /**
     * Render the terrific stylesheet or script
     */
    public static function render() {
    	$CACHE_ENABLED = get_option('tc_cacheenabled');
		$MAX_CACHE_AGE = get_option('tc_maxcacheage');

		if(get_option('tc_usewpjquery', null) == null){
			update_option('tc_usewpjquery', true);
		}
	
		if (isset($_GET["css"])) {
			$cache_dir = Terrific::basePath() . 'cache/';
			$file = $cache_dir . 'app.css';
			if (is_file($file) && $CACHE_ENABLED) {
				$last_modified_time = date('Y-m-d H:i:s', filemtime($file));
				if(strtotime(date('Y-m-d H:i:s')) - strtotime($last_modified_time) < $MAX_CACHE_AGE){
					header("Content-Type: text/css");
					echo file_get_contents($file);
					exit();
				}
			}
			Terrific::dump('css', 'text/css');
			exit();
		}
		if (isset($_GET["js"])) {
			
			$cache_dir = Terrific::basePath() . 'cache/';
			$file = $cache_dir . 'app.js';
			if (is_file($file) && $CACHE_ENABLED) {
				$last_modified_time = date('Y-m-d H:i:s', filemtime($file));
				if(strtotime(date('Y-m-d H:i:s')) - strtotime($last_modified_time) < $MAX_CACHE_AGE){
					header("Content-Type: text/javascript; charset=utf-8");
					echo file_get_contents($file);
					exit();
				}
			}
			Terrific::dump('js', 'text/javascript');
			exit();
		}
    }
	
	/**
	 * Clear cache folder
	 */
	public static function flushCache() {
		foreach (glob(Terrific::basePath() . 'cache/*') as $entry) {
			@unlink($entry);
		}
	}
	
	/**
	 * Compile a CSS/LESS/SCSS file.
	 */
	public static function compile($filename, $extension, $base = false) {
		switch ($extension) {
			case 'less':
				require_once plugin_dir_path(__FILE__) . '/library/lessphp/lessc.inc.php';
				$less = new lessc;
				$content = $less->compileFile($filename);
				break;
			case 'scss':
				require_once plugin_dir_path(__FILE__) . '/library/phpsass/SassParser.php';
				$sass = new SassParser(array('style'=>'nested', 'cache' => false));
				$content = $sass->toCss($filename);
				break;
			default:
				$content = file_get_contents($filename);
				break;
		}
		return $content;
	}

	/**
	 * Dump CSS/JS.
	 */
	public static function dump($extension, $mimetype) {	
		$cache_dir = Terrific::basePath() . 'cache/';
		@unlink($cache_dir.'app.'.$extension);
		$CACHE_ENABLED = get_option('tc_cacheenabled');
		
		$formats = array(
			'js' => array('js'),
			'css' => array('less', 'scss', 'css')
		);
		$files = array();
		$output = "";

		// Include the WordPress jQuery file
		if(get_option('tc_usewpjquery') && $extension == 'js'){
			$output .= file_get_contents(includes_url().'/js/jquery/jquery.js');
			$output .= 'var $ = jQuery;';
		}

		$assets = json_decode(file_get_contents(Terrific::basePath() . 'assets/assets.json'));
		// Get all asset files of the given format
		foreach ($assets->$extension as $pattern) {
			foreach (glob(Terrific::basePath() . 'assets/' . $extension . '/' . $pattern) as $entry) {
				if (is_file($entry) && !array_key_exists($entry, $files)) {
					$format = substr(strrchr($entry, '.'), 1);
					$output .= Terrific::compile($entry, $format, true);
					$files[$entry] = true;
				}
			}
		}
		
		// Get all module files of the given format
		foreach (glob( Terrific::basePath() . 'modules/*', GLOB_ONLYDIR) as $dir) {
			$module = basename($dir);
			foreach ($formats[$extension] as $format) {
				// Get main module file (e.g.: teaserbox.css, or TeaserBox.css if the first doesn't exist)
				$entry = $dir . '/' . $extension . '/' . strtolower($module) . '.' . $format;
				if (!array_key_exists($entry, $files)) {
					if(is_file($entry)){
						$output .= Terrific::compile($entry, $format) ."\n";
						$files[strtolower($entry)] = true;
					}else{
						$entry = $dir . '/' . $extension . '/' . $module . '.' . $format;
						if(is_file($entry)){
							$output .= Terrific::compile($entry, $format) ."\n";
							$files[strtolower($entry)] = true;
						}
					}
				}

				// Recursively get files in subdirectories
				$output .= Terrific::dumpDir($extension, $format, $dir . '/' . $extension, $files);
			}
		}

		// Minify the result if caching is enabled
		if ($CACHE_ENABLED) {
			switch ($extension) {
				case 'css':
					require plugin_dir_path(__FILE__) . '/library/cssmin/cssmin.php';
					$output = CssMin::minify($output);
					break;
				case 'js':
					require plugin_dir_path(__FILE__) . '/library/jsmin/jsmin.php';
					$output = JsMin::minify($output);
					break;
			}
		}
		header("Content-Type: " . $mimetype);
		
		file_put_contents($cache_dir.'app.'.$extension, $output);
		
		echo $output;
	}

	public static function dumpDir($extension, $format, $dirPath, $files){
		$output = '';
		if (is_dir($dirPath) && $handle = opendir($dirPath)) {
		    while(false !== ($file = readdir($handle))) {
		    	if ($file == '.' or $file == '..') continue;

		    	$file_parts = pathinfo($file);
		    	$filePath = $dirPath.'/'.$file;
		    	if (is_file($filePath) && $file_parts['extension'] == $format && !array_key_exists(strtolower($filePath), $files)) {
					$output .= Terrific::compile($filePath, $format) ."\n";
					$files[strtolower($filePath)] = true;
				}

				if(is_dir($filePath)){
					$output .= Terrific::dumpDir($extension, $format, $filePath, $files);
				}
		    }
		    closedir($handle);
		}
		return $output;
	}

	/**
	 * Render module markup.
	 */
	public static function module($name, $template = null, $skin = null, $attr = array()) {
		$flat = strtolower($name);
		$dashed = Terrific::dashCamelCase($name);
		$template = $template == null ? '' : '-' . $template;
		$skin = $skin == null ? '' : ' skin-' . $dashed . '-' . Terrific::dashCamelCase($skin);
		$attributes = " ";
		foreach ($attr as $key => $value) {
			$attributes .= $key . '="' . $value . '" ';
		}
		echo "<div class=\"mod mod-" . $dashed . $skin . "\"" . chop($attributes) . ">" . "\n";
		require Terrific::basePath() . 'modules/' . $name . '/' . $flat . $template . '.php';
		echo "\n</div>";
	}
	
	/**
	 * Register terrific stylesheet
	 */
	public static function terrific_styles() {
		// Add terrific css
		wp_enqueue_style('terrific', get_template_directory_uri().'/tc.php?css');
	}
	
	/**
	 * Register terrific admin stylesheet
	 */
	public static function terrific_admin_styles() {
		// Add terrific css
		wp_enqueue_style('terrific', plugins_url('style.css', __FILE__));
	}

	/**
	 * Append terrific footer
	 */
	public static function terrific_footer() {
		// Add terrific js
		?>
			<script src="<?php echo get_template_directory_uri().'/tc.php?js' ?>"></script>
			<script>
			(function($) {
				$(document).ready(function() {
					var application = new Tc.Application($('html'), {});
					application.registerModules();
					application.start();
				});
			})(Tc.$);
			</script>
		<?php
	}

	/**
	 * Add terrific admin menu entries
	 */
	public static function terrific_adminbar_menu() {
		global $wp_admin_bar;
		
		?>
			<script>
				(function($) {
					$(document).ready(function() {
						$('.tc_inspect').on('click', function(){
							if($('.tc_inspectmodule').size() > 0){
								$('.tc_inspectmodule').remove();
							}else{
								$('.mod').each(function(){
									var mod = $(this);
									var width = mod.outerWidth(),
									height = mod.outerHeight(),
									top = mod.position().top,
									left = mod.position().left,
									name = mod.attr('class');
									var classes = mod.attr('class').split(' ');
									for(var i = 0; i < classes.length; i++){
										if(classes[i].split('mod-')[1]){
											name = classes[i].split('mod-')[1];
											break;
										}
									}
									$('body').append('<div class="tc_inspectmodule" style="width: ' + width + 'px; height: ' + height + 'px; top: ' + top + 'px; left: ' + left + 'px;"><span>' + name + '</span></div>');
								});
							}
							return false;
						});
					});
				})(jQuery);
			</script>
			<style>
				.tc_inspectmodule {
					display: block;
					position: absolute;
					border: 1px solid #333;
					background: #fff;
					opacity: 0.8;
					-webkit-box-shadow: 0 3px 10px rgba(0,0,0,0.4);
					-moz-box-shadow: 0 3px 10px rgba(0,0,0,0.4);
					-o-box-shadow: 0 3px 10px rgba(0,0,0,0.4);
					-ms-box-shadow: 0 3px 10px rgba(0,0,0,0.4);
					box-shadow: 0 3px 10px rgba(0,0,0,0.4);
					font: 12px/1.2em 'Helvetica Neue', Helvetica, Arial, sans-serif;
					letter-spacing: 0.03em;
					min-height: 2.2em;
					outline: none;
					-webkit-transition: all 0.2s ease-in-out;
					-moz-transition: all 0.2s ease-in-out;
					-o-transition: all 0.2s ease-in-out;
					-ms-transition: all 0.2s ease-in-out;
					transition: all 0.2s ease-in-out;
				}
				.tc_inspectmodule:hover {
					opacity: 0.1;
				}
				.tc_inspectmodule span {
					text-transform: capitalize;
					display: inline-block;
					background: #000;
					color: #efefef;
					padding: 0.4em 0.5em 0.6em 0.5em;
					vertical-align: top;
					-webkit-transition: all 0.4s ease-in-out;
					-moz-transition: all 0.4s ease-in-out;
					-o-transition: all 0.4s ease-in-out;
					-ms-transition: all 0.4s ease-in-out;
					transition: all 0.4s ease-in-out;
				}
			</style>
		<?php

		$wp_admin_bar->add_node(
			array(	'id' => 'terrific',
					'title' => __( 'Terrific' ),
					'href' => get_admin_url().'admin.php?page=terrific-integration'
			)
		);
		
		$wp_admin_bar->add_node(
			array(	'id' => 'terrific-addModuleSkin',
					'title' => __( 'Add Modules/Skins' ),
					'href' => get_admin_url().'admin.php?page=terrific-integration',
					'parent' => 'terrific'
			)
		);
		
		$wp_admin_bar->add_node(
			array(	'id' => 'terrific-inspect',
					'title' => __( 'Inspect' ),
					'href' => '#',
					'parent' => 'terrific',
					
					'meta'     => array(
						'class' => 'tc_inspect'
					)
			)
		);
		
		$wp_admin_bar->add_node(
			array(	'id' => 'terrific-flush',
					'title' => __( 'Flush Terrific Cache' ),
					'href' => '?tc_flush',
					'parent' => 'terrific'
			)
		);
	}

	/**
	 * Create options menu
	 */
	public static function createMenu() {
		//create top-level menu
		add_menu_page('Terrific Integration', 'Terrific', 'administrator', __FILE__, 'Terrific::pluginPage', plugins_url('/icon.png', __FILE__));
	}
	
	/**
	 * Split CamelCase-string and concatenate the parts with -
	 */
	public static function dashCamelCase($text) {
		return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1-\\2', '\\1-\\2'), $text));
	}
	
	/**
	 * Create new module
	 */
	public static function createModule() {
		$form = $_POST['tc_newmodule'];
		$module = $form['name'];
		$modDir = Terrific::basePath().'modules/'.$module;
		$dashed = Terrific::dashCamelCase($module);
		$cssModule = 'mod-' . $dashed;
	
		if(!is_dir($modDir)){
			mkdir($modDir);
			
			// Create template file
			ob_start();
			include plugin_dir_path(__FILE__).'/templates/module.php';
			$buffer = ob_get_clean();
			file_put_contents($modDir.'/'.strtolower($module).'.php', $buffer);
			
			// Create css & js directories
			$cssDir = $modDir.'/css';
			$jsDir = $modDir.'/js';
			if(!is_dir($cssDir)){
				mkdir($cssDir);
			}
			if(!is_dir($jsDir)){
				mkdir($jsDir);
			}
			
			// Create js file
			ob_start();
			include plugin_dir_path(__FILE__).'/templates/js.php';
			$buffer = ob_get_clean();
			file_put_contents($jsDir.'/'.$module.'.js', $buffer);
			
			// Create stylesheet file
			ob_start();
			include plugin_dir_path(__FILE__).'/templates/'.$form['style'].'.php';
			$buffer = ob_get_clean();
			file_put_contents($cssDir.'/'.strtolower($module).'.'.$form['style'], $buffer);
			
			?> <span class="info">Module '<?php echo $module; ?>' created.</span> <?php
		}else{
			?> <span class="alert">Module '<?php echo $module; ?>' already exists.</span> <?php
		}
	}
	
	/**
	 * Recursively copies entire directories to destination
	 */
	public static function recurse_copy($src, $dst) { 
		$dir = opendir($src); 
		@mkdir($dst); 
		while(false !== ( $file = readdir($dir)) ) { 
			if (( $file != '.' ) && ( $file != '..' ) && ( $file != '.gitignore' )) { 
				if ( is_dir($src . '/' . $file) ) { 
					Terrific::recurse_copy($src . '/' . $file,$dst . '/' . $file); 
				} 
				else { 
					copy($src . '/' . $file,$dst . '/' . $file);
				} 
			} 
		} 
		closedir($dir); 
		return true;
	} 
	
	/**
	 * Create new skin
	 */
	public static function createSkin() {
		$form = $_POST['tc_newskin'];
		$module = $form['module'];
		$modDir = Terrific::basePath().'modules/'.$module;
		$skin = $form['name'];
		$dashed = Terrific::dashCamelCase($module);
		$cssModule = 'mod-' . $dashed;
		$cssSkin = 'skin-' . $dashed . '-' . Terrific::dashCamelCase($skin);
	
		if(is_dir($modDir)){
			// Create css & js skin directories
			$cssDir = $modDir.'/css';
			$jsDir = $modDir.'/js';
			$cssSkinDir = $modDir.'/css/skin';
			$jsSkinDir = $modDir.'/js/skin';
			
			if($form['createcss'] == 'css'){
				if(!is_dir($cssDir)){
					mkdir($cssDir);
				}
				if(!is_dir($cssSkinDir)){
					mkdir($cssSkinDir);
				}
				// Create stylesheet file
				$filePath = $cssSkinDir.'/'.strtolower($skin).'.'.$form['style'];
				if(!file_exists($filePath)){
					ob_start();
					include plugin_dir_path(__FILE__).'/templates/skin_'.$form['style'].'.php';
					$buffer = ob_get_clean();
					file_put_contents($filePath, $buffer);
				}else{
					?> <span class="alert">Skin style file already exists.</span> <?php
				}
			}
			
			if($form['createjs'] == 'js'){
				if(!is_dir($jsDir)){
					mkdir($jsDir);
				}
				if(!is_dir($jsSkinDir)){
					mkdir($jsSkinDir);
				}
				// Create js file
				$filePath = $jsSkinDir.'/'.$module.'.'.$skin.'.js';
				if(!file_exists($filePath)){
					ob_start();
					include plugin_dir_path(__FILE__).'/templates/skin_js.php';
					$buffer = ob_get_clean();
					file_put_contents($filePath, $buffer);
				}else{
					?> <span class="alert">Skin script file already exists.</span> <?php
				}
			}
			
			?> <span class="info">Skin '<?php echo $skin; ?>' created.</span> <?php
		}else{
			?> <span class="alert">Module '<?php echo $module; ?>' does not exist.</span> <?php
		}
	}
}

class TerrificPluginPage
{
    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    }

    /**
     * Add plugin page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_menu_page(
            'Settings Admin', 
            'Terrific', 
            'manage_options', 
            'terrific-integration', 
            array( $this, 'create_admin_page' ),
			plugins_url('/icon.png', __FILE__)
        );

    }

    /**
     * Plugin page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'my_option_name' );
        ?>
        <div class="tc_admin wrap">
			<script>
				(function($) {
					$(document).ready(function() {
						$('.createcss').on('change', function(){
							$('.styletype').toggle();
							return false;
						});
					});
				})(jQuery);
			</script>
            <img class="pluginicon" src="<?php echo plugins_url('/plugin-icon.png', __FILE__); ?>" alt=""/>
            <h1>Terrific Integration</h1>
			<?php
			
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tc_createbasicstructure'])){
				add_option('tc_cacheenabled', false);
				add_option('tc_maxcacheage', 86400);
				Terrific::recurse_copy(plugin_dir_path(__FILE__).'/terrific-structure/modules', get_template_directory().'/modules');
				Terrific::recurse_copy(plugin_dir_path(__FILE__).'/terrific-structure/assets', get_template_directory().'/assets');
				Terrific::recurse_copy(plugin_dir_path(__FILE__).'/terrific-structure/cache', get_template_directory().'/cache');
				copy(plugin_dir_path(__FILE__).'/terrific-structure/tc.php', get_template_directory().'/tc.php');
			}
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tc_createnewtheme'])){
				add_option('tc_cacheenabled', false);
				add_option('tc_maxcacheage', 86400);
				if(Terrific::recurse_copy(plugin_dir_path(__FILE__).'/terrific-theme', get_theme_root().'/terrific-theme')){
					switch_theme('terrific-theme');
				}
			}
			
			if(Terrific::isEnabled()){
				if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tc_settings'])){
					$form = $_POST['tc_settings'];
					
					if($form['usewpjquery'] == 'true'){
						update_option('tc_usewpjquery', true);
					}else{
						update_option('tc_usewpjquery', false);
					}
					if($form['cacheenabled'] == 'true'){
						update_option('tc_cacheenabled', true);
					}else{
						update_option('tc_cacheenabled', false);
					}
					if($form['maxcacheage'] >= 0){
						update_option('tc_maxcacheage', $form['maxcacheage']);
					}else{
						update_option('tc_maxcacheage', $form['maxcacheage']);
					}
				}
				if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tc_newmodule'])){
					Terrific::createModule();
				}
				if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tc_newskin'])){
					Terrific::createSkin();
				}
				
				
			?>
			
			<h2>Settings</h2>
            <form class="settings" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<p>
					<label for="tc_settings_usewpjquery">Use WordPress jQuery:</label><input type="checkbox" <?php if(get_option('tc_usewpjquery')){ echo 'checked'; } ?> id="tc_settings_usewpjquery" name="tc_settings[usewpjquery]" value="true">
				</p>
				<p>
					<label for="tc_settings_cacheenabled">Enable Cache:</label><input type="checkbox" <?php if(get_option('tc_cacheenabled')){ echo 'checked'; } ?> id="tc_settings_cacheenabled" name="tc_settings[cacheenabled]" value="true">
				</p>
				<p>
					<label for="tc_settings_maxcacheage">Max. Cache Age:</label><input class="maxcacheage" type="text" id="tc_settings_maxcacheage" name="tc_settings[maxcacheage]" value="<?php echo get_option('tc_maxcacheage'); ?>" /> seconds
				</p>
				<p>
					<input type="submit" name="submit" class="button button-primary" value="Save Settings">
				</p>
            </form>
			
			<h2>Create Structure</h2>
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<h3>Create Module</h3>
				<p>
					<input type="text" name="tc_newmodule[name]" placeholder="Module Name (e.g.: TeaserBox)" value="" />
				</p>
				<p>
					<input type="radio" id="tc_addmodule_css" checked name="tc_newmodule[style]" value="css"><label for="tc_addmodule_css">CSS</label>
					<input type="radio" id="tc_addmodule_less" name="tc_newmodule[style]" value="less"><label for="tc_addmodule_less">LESS</label>
					<input type="radio" id="tc_addmodule_scss" name="tc_newmodule[style]" value="scss"><label for="tc_addmodule_scss">SCSS</label>
				</p>
				<p>
					<input type="submit" name="submit" class="button button-primary" value="Add Module">
				</p>
            </form>
			
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<h3>Add Skin</h3>
				<p>
					<input type="text" name="tc_newskin[name]" placeholder="Skin Name (e.g.: DarkBlue)" value="" /> 
				</p>
				<p>
					<label for="tc_addskin_module">Module: </label>
					<select id="tc_addskin_module" name="tc_newskin[module]">
						<?php 
						foreach (glob( Terrific::basePath() . 'modules/*', GLOB_ONLYDIR) as $dir) {
							$module = basename($dir);
							?> <option value="<?php echo $module ?>"><?php echo $module ?></option> <?php
						}
						?>
					</select>
				</p>
				<p>
					<input class="createcss" type="checkbox" id="tc_addskin_createcss" checked name="tc_newskin[createcss]" value="css"><label for="tc_addskin_createcss">Create CSS</label>
					<input type="checkbox" id="tc_addskin_createjs" checked name="tc_newskin[createjs]" value="js"><label for="tc_addskin_createjs">Create JS</label>
				</p>
				<div class="styletype">
					<h4>Stylesheet type</h4>
					<input type="radio" id="tc_addskin_css" checked name="tc_newskin[style]" value="css"><label for="tc_addskin_css">CSS</label>
					<input type="radio" id="tc_addskin_less" name="tc_newskin[style]" value="less"><label for="tc_addskin_less">LESS</label>
					<input type="radio" id="tc_addskin_scss" name="tc_newskin[style]" value="scss"><label for="tc_addskin_scss">SCSS</label>
				</div>
				<p>
					<input type="submit" name="submit" class="button button-primary" value="Add Skin">
				</p>
            </form>
        </div>
        <?php
			}else{
		?>
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<h3>Create Terrific Structure</h3>
				<p>
					Your active theme doesn't seem to contain a valid terrific structure.<br>
					Let the plugin create an empty basic structure in your active theme or let it create a completely new theme containing basic modules and templates for you.<br>
					If you already created your theme, make sure it's activated.
				</p>
				<p>
					<input type="submit" name="tc_createbasicstructure" class="button button-primary" value="Create Basic Structure"> <input type="submit" name="tc_createnewtheme" class="button button-primary" value="Create New Theme">
				</p>
            </form>
		<?php
			}
    }
}

// Called in template files to render a module
function module($name, $template = null, $skin = null, $attr = array()) {
    Terrific::module($name, $template, $skin, $attr);
}

if( is_admin() ){
	new TerrificPluginPage();
	add_action('admin_head', 'Terrific::terrific_admin_styles');
}

if(Terrific::isEnabled()){
	add_action('admin_bar_menu', 'Terrific::terrific_adminbar_menu', 20);
	add_action('wp_enqueue_scripts', 'Terrific::terrific_styles');
	add_action('wp_footer', 'Terrific::terrific_footer');
	add_action('init', 'Terrific::hook', 0);
}

?>