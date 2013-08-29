<?php

/**
 * Code optimizer object is used to compile JavaScript and CSS. By reducting its
 * size, web page responsivness is considerably increased.
 *
 * There's no need to use this class manually as both template handler and head tag
 * module will automatically use this class if configured.
 */

require_once('closure.php');
require_once('lessc.php');


class CodeOptimizer {
	private static $_instance;

	private $script_list = array();
	private $style_list = array();
	private $style_secondary_list = array();  // list populated with @import

	// compilers
	private $closure_compiler = null;
	private $less_compiler = null;

	const LEVEL_NONE = 0;
	const LEVEL_BASIC = 1;
	const LEVEL_ADVANCED = 2;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->closure_compiler = new PhpClosure();
	}

	/**
	 * Generate name based on list of URL's
	 *
	 * @param array $list
	 * @return string
	 */
	private function _getCachedName($list) {
		$all_files = implode($list);
		return md5($all_files);
	}

	/**
	 * Check if cache file needs to be recompiled.
	 *
	 * @param string $file_name
	 * @param array $list
	 * @return boolean
	 */
	private function _needsRecompile($file_name, $list) {
		$result = false;

		// check if file exists
		if (!file_exists($file_name))
			$result = true; else
			$cache_time = filemtime($file_name);

		// check each individual file
		if (!$result)
			foreach ($list as $file) 
				if (filemtime($file) > $cache_time) {
					$result = true;
					break;
				}

		return $result;
	}

	/**
	 * Inlude style file.
	 *
	 * @param string $file_name
	 * @param array $additional_imports
	 * @param array $priority_commands
	 * @return string
	 */
	private function _includeStyle($file_name, &$additional_imports, &$priority_commands) {
		$result = array();
		$data = file_get_contents($file_name);

		switch (pathinfo($file_name, PATHINFO_EXTENSION)) {
			case 'less':
				// create compiler if we need it
				if (is_null($this->less_compiler))
					$this->less_compiler = new lessc();

				try {
					$result = $this->less_compiler->compile($data);
				} catch (Exception $error) {
					trigger_error('Error compiling: '.$file_name, E_USER_NOTICE);
				}

				break;

			case 'css':
			default:
				// parse most important
				$data = str_replace("\r", "", $data);
				$data = explode("\n", $data);

				$in_comment = false;

				foreach($data as $line) {
					$line_data = trim($line);
					$command = explode(" ", $line_data);

					// skip empty lines
					if (empty($line_data))
						continue;

					// handle each command individually
					switch (strtolower($command[0])) {
						case '@import':
							if (substr($command[1], 0, 3) == 'url')
								$priority_commands []= $line_data; else
								$additional_imports []= dirname($file_name).'/'.trim($command[1], '";');
								
							break;

						case '@charset':
							array_unshift($priority_commands, $line_data);
							break;

						default:
							$result []= $line_data;
					}
				}
		}

		return $result;
	}

	/**
	 * Compile styles.
	 *
	 * @param string $file_name
	 * @param array $list
	 */
	private function _recompileStyles($file_name, $list) {
		global $cache_path;

		$result = array();
		$additional_files = array();
		$priority_commands = array();

		// gather data
		foreach($list as $original_file) {
			$file_result = $this->_includeStyle($original_file, $additional_files, $priority_commands);
			$result = array_merge($result, $file_result);
		}

		if (count($additional_files) > 0)
			foreach($additional_files as $file) {
				$file_result = $this->_includeStyle($file, $additional_files, $priority_commands);
				$result = array_merge($result, $file_result);
			}

		// insert priority commands at the top of the file
		$result = array_merge($priority_commands, $result);

		// save compiled file
		file_put_contents($file_name, implode(" ", $result));
	}

	/**
	 * Get a single instance of this object.
	 * @return object
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Add script to be compiled.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function addScript($url) {
		global $section;

		if (!in_array($section, array('backend', 'backend_module'))) {
			// add script to be compiled
			$this->script_list []= $url;
			$this->closure_compiler->add($url);

		} else {
			// we do not need to compile code in backend
			$result = false;
		}

		return true;
	}

	/**
	 * Add style to be compiled.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function addStyle($url) {
		global $module_path;

		$result = false;
		$url_base = _BASEURL.'/'.$module_path;

		// only add styles that are site specific
		if (substr($url, 0, strlen(_BASEURL)) == _BASEURL && substr($url, 0, strlen($url_base)) != $url_base) {
			$this->style_list []= $url;
			$result = true;
		}

		return $result;
	}

	/**
	 * Return compiled scripts and styles.
	 *
	 * @return string
	 */
	public function printData() {
		global $cache_path;

		// compile styles if needed
		$style_cache = $cache_path.$this->_getCachedName($this->style_list).'.css';
		if ($this->_needsRecompile($style_cache, $this->style_list)) 
			$this->_recompileStyles($style_cache, $this->style_list);

		// compile scripts
		$script_cache = $this->closure_compiler
				->quiet()
				->hideDebugInfo()
	 			->simpleMode()
				->cacheDir($cache_path)
				->compileToFile();

		print '<link type="text/css" rel="stylesheet" href="'._BASEURL.'/'.$style_cache.'">';
		print '<script type="text/javascript" async src="'._BASEURL.'/'.$script_cache.'"></script>';
	}
}

?>
