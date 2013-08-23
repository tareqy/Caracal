<?php

/**
 * Page Information
 *
 * This module integrates some of the basic elements of the web site
 * pages. It also provides support for Google Webmasters Tools and Analytics.
 *
 * Author: Mladen Mijatov
 */

class page_info extends Module {
	private static $_instance;
	private $omit_elements = array();
	private $optimizer_page = '';
	private $optimizer_show_control = false;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section, $db_use;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$menu = $backend->getMenu($backend->name);

			if (!is_null($menu))
				$menu->insertChild(new backend_MenuItem(
										$this->getLanguageConstant('menu_page_info'),
										url_GetFromFilePath($this->path.'images/icon.png'),
										window_Open( // on click open window
													'page_settings',
													400,
													$this->getLanguageConstant('title_page_info'),
													true, false, // disallow minimize, safety feature
													backend_UrlMake($this->name, 'show')
												),
										$level=5
									), 1);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) { 
				case 'set_omit_elements':
					$this->omit_elements = fix_chars(explode(',', $params['elements']));
					break;

				case 'set_optimizer_page':
					$this->optimizer_page = fix_chars($params['page']);
					if (isset($params['show_control']))
						$this->optimizer_show_control = fix_id($params['show_control']) == 0 ? false : true;
					break;

				default:
					break;
			}

		// backend control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'show':
					$this->showSettings();
					break;

				case 'save':
					$this->saveSettings();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		if (!isset($this->settings['description']))
			$this->saveSetting('description', '');

		if (!isset($this->settings['analytics']))
			$this->saveSetting('analytics', '');

		if (!isset($this->settings['wm_tools']))
			$this->saveSetting('wm_tools', '');
	}

	public function onDisable() {
	}

	/**
	 * Show settings form
	 */
	private function showSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
						'form_action'	=> backend_UrlMake($this->name, 'save'),
						'cancel_action'	=> window_Close('page_settings')
					);

		$template->registerTagHandler('cms:analytics_versions', $this, 'tag_AnalyticsVersions');

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save settings
	 */
	private function saveSettings() {
		$description = fix_chars($_REQUEST['description']);
		$analytics = fix_chars($_REQUEST['analytics']);
		$analytics_domain = fix_chars($_REQUEST['analytics_domain']);
		$analytics_version = fix_chars($_REQUEST['analytics_version']);
		$wm_tools = fix_chars($_REQUEST['wm_tools']);
		$optimizer = fix_chars($_REQUEST['optimizer']);
		$optimizer_key = fix_chars($_REQUEST['optimizer_key']);

		$this->saveSetting('description', $description);
		$this->saveSetting('analytics', $analytics);
		$this->saveSetting('analytics_domain', $analytics_domain);
		$this->saveSetting('analytics_version', $analytics_version);
		$this->saveSetting('wm_tools', $wm_tools);
		$this->saveSetting('optimizer', $optimizer);
		$this->saveSetting('optimizer_key', $optimizer_key);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close('page_settings')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/** 
	 * Method called by the page module to add elements before printing
	 */
	public function addElements() {
		global $section, $db_use;

		$head_tag = head_tag::getInstance();
		$collection = collection::getInstance();
		$language_list = MainLanguageHandler::getInstance()->getLanguages(false);

		// add base url tag
		$head_tag->addTag('base', array('href' => _BASEURL));

		// content meta tags
		if (!in_array('content_type', $this->omit_elements)) {
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Type',
							'content'		=> 'text/html; charset=UTF-8'
						));
			header('Content-Type: text/html; charset=UTF-8');
		}

		if (!in_array('viewport', $this->omit_elements) && !_DESKTOP_VERSION) 
			$head_tag->addTag('meta',
						array(
							'name'		=> 'viewport',
							'content'	=> 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0'
						));

		if (!in_array('language', $this->omit_elements))
			$head_tag->addTag('meta',
						array(
							'http-equiv'	=> 'Content-Language',
							'content'		=> join(', ', $language_list)
						));

		// robot tags
		$head_tag->addTag('meta', array('name' => 'robots', 'content' => 'index, follow'));
		$head_tag->addTag('meta', array('name' => 'googlebot', 'content' => 'index, follow'));
		$head_tag->addTag('meta', array('name' => 'rating', 'content' => 'general'));

		if ($section != 'backend' && $section != 'backend_module' && $db_use) {
			// google analytics
			if (!empty($this->settings['analytics']))
				$head_tag->addGoogleAnalytics(
										$this->settings['analytics'],
										$this->settings['analytics_domain'],
										$this->settings['analytics_version']
									);

			// google website optimizer
			if (!empty($this->settings['optimizer']))
				$head_tag->addGoogleSiteOptimizer(
										$this->settings['optimizer'],
										$this->settings['optimizer_key'],
										$this->optimizer_page,
										$this->optimizer_show_control
									);

			// google webmasters tools
			if (!empty($this->settings['wm_tools']))
				$head_tag->addTag('meta',
							array(
								'name' 		=> 'google-site-verification',
								'content' 	=> $this->settings['wm_tools']
							));

			// page description
			if ($db_use) 
				$head_tag->addTag('meta',
							array(
								'name'		=> 'description',
								'content'	=> $this->settings['description']
							));
		}

  		// copyright
		if (!in_array('copyright', $this->omit_elements)) {
			$copyright = MainLanguageHandler::getInstance()->getText('copyright');
			$copyright = strip_tags($copyright);
			$head_tag->addTag('meta',
						array(
							'name'		=> 'copyright',
							'content'	=> $copyright
						));
		}				

		// favicon
		if (file_exists(_BASEPATH.'/images/favicon.png'))
			$icon_file = _BASEPATH.'/images/favicon.png'; else
			$icon_file = _BASEPATH.'/images/default_icon.png';

		$head_tag->addTag('link',
					array(
						'rel'	=> 'icon',
						'type'	=> 'image/png',
						'href'	=> url_GetFromFilePath($icon_file)
					));

		// add default styles and script if they exists
		$collection->includeScript(collection::JQUERY);

		if ($section != 'backend') {
			$styles = array();

			// prepare list of files without extensions
			if (_DESKTOP_VERSION) {
				$styles = array(
						'/styles/common',
						'/styles/main',
						'/styles/header',
						'/styles/content',
						'/styles/footer'
					);
			} else {
				$styles = array(
						'/styles/common',
						'/styles/main',
						'/styles/header_mobile',
						'/styles/content_mobile',
						'/styles/footer_mobile'
					);
			}

			// include styles
			$less_included = false;

			foreach ($styles as $style) {
				// check for css files
				if (file_exists(_BASEPATH.$style.'.css'))
					$head_tag->addTag('link',
							array(
								'rel'	=> 'stylesheet',
								'type'	=> 'text/css',
								'href'	=> url_GetFromFilePath(_BASEPATH.$style.'.css')
							));

				// check for less files
				if (file_exists(_BASEPATH.$style.'.less')) {
					$head_tag->addTag('link',
							array(
								'rel'	=> 'stylesheet/less',
								'type'	=> 'text/css',
								'href'	=> url_GetFromFilePath(_BASEPATH.$style.'.less')
							));

					if (!$less_included) {
						$collection->includeScript(collection::LESS);
						$less_included = true;
					}
				}
			}

			// add main javascript
			if (file_exists(_BASEPATH.'/scripts/main.js'))
				$head_tag->addTag('script',
						array(
							'type'	=> 'text/javascript',
							'src'	=> url_GetFromFilePath(_BASEPATH.'/scripts/main.js')
						));
		}
	}

	/**
	 * Show list of available analytics versions.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_AnalyticsVersions($tag_params, $children) {
		// load template
		$template = $this->loadTemplate($tag_params, 'analytics_version.xml');
		$selected = isset($tag_params['selected']) ? $tag_params['selected'] : '0';

		// get available versions
		$versions = array();
		$files = scandir(_BASEPATH.'/modules/head_tag/templates/');
		trigger_error(print_r($files, true));

		foreach ($files as $file) 
			if (substr($file, 0, 16) == 'google_analytics')
				$versions[] = substr(basename($file, '.xml'), 17);

		// show options
		if (count($versions) > 0)
			foreach ($versions as $version) {
				$params = array(
						'version'	=> $version,
						'selected'	=> $selected
					);
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}
}
