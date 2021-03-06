<?php

/**
 * Comments Module
 * This module is designed to be easily implementable anywhere.
 *
 * Author: Mladen Mijatov
 * @todo Module is not finished.
 */
use Core\Module;


class comments extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// register backend
		if ($section == 'backend' && class_exists('backend')) {
			$backend = backend::getInstance();

			$comments_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_comments'),
					url_GetFromFilePath($this->path.'images/icon.svg'),
					'javascript:void(0);',
					$level=5
				);

			$comments_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_administration'),
								url_GetFromFilePath($this->path.'images/administration.svg'),
								window_Open( // on click open window
											'links_list',
											730,
											$this->getLanguageConstant('title_links_manage'),
											true, true,
											backend_UrlMake($this->name, 'links_list')
										),
								$level=5
							));

			$comments_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_settings'),
								url_GetFromFilePath($this->path.'images/settings.svg'),
								window_Open( // on click open window
											'comments_settings',
											400,
											$this->getLanguageConstant('title_settings'),
											true, true,
											backend_UrlMake($this->name, 'settings')
										),
								$level=5
							));

			$backend->addMenu($this->name, $comments_menu);
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
	public function transferControl($params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show_input_form':
					$this->showInputForm($params);
					break;

				case 'save_data':
					$this->saveCommentData();
					break;

				case 'get_data':
					$this->printCommentData();
					break;

				case 'show_section':
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'settings':
					$this->moduleSettings();
					break;

				case 'settings_save':
					$this->moduleSettings_Save();
					break;

				case 'comments':
					$this->showComments();
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db;

		$sql = "
			CREATE TABLE `comments` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`module` VARCHAR( 50 ) NOT NULL ,
				`section` VARCHAR( 32 ) NOT NULL ,
				`user` VARCHAR( 50 ) NOT NULL ,
				`email` VARCHAR( 50 ) NULL ,
				`address` VARCHAR( 15 ) NOT NULL ,
				`message` TEXT NOT NULL ,
				`timestamp` TIMESTAMP NOT NULL ,
				`visible` BOOLEAN NOT NULL ,
				PRIMARY KEY ( `id` ) ,
				INDEX ( `module` , `section` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		$db->query($sql);

		if (!array_key_exists('repost_time', $this->settings))
			$this->saveSetting('repost_time', 15);

		if (!array_key_exists('default_visibility', $this->settings))
			$this->saveSetting('default_visibility', 1);

		if (!array_key_exists('size_limit', $this->settings))
			$this->saveSetting('size_limit', 200);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db;

		$tables = array('comments');
		$db->drop_tables($tables);
	}

	/**
	 * Show comments management form
	 */
	private function showComments() {
		$comments_module = isset($_REQUEST['comments_module']) ? fix_chars($_REQUEST['comments_module']) : null;
		$comments_section = isset($_REQUEST['comments_section']) ? fix_chars($_REQUEST['comments_section']) : null;
		$manager = CommentManager::getInstance();
	}

	/**
	 * Show module settings form
	 */
	private function moduleSettings() {
		$template = new TemplateHandler('settings.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'settings_save'),
					'cancel_action'	=> window_Close('comments_settings')
				);

		$params = array_merge($params, $this->settings);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save module settings
	 */
	private function moduleSettings_Save() {
		$repost_time = isset($_REQUEST['repost_time']) ? fix_id($_REQUEST['repost_time']) : 15;
		$default_visibility = isset($_REQUEST['default_visibility']) ? fix_id($_REQUEST['default_visibility']) : 1;
		$size_limit = isset($_REQUEST['size_limit']) ? fix_id($_REQUEST['size_limit']) : 200;

		$this->saveSetting('default_visibility', $default_visibility);
		$this->saveSetting('repost_time', $repost_time);
		$this->saveSetting('size_limit', $size_limit);

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant('message_settings_saved'),
					'button'		=> $this->getLanguageConstant('close'),
					'action'		=> window_Close('comments_settings')
				);
		$params = array_merge($params, $this->settings);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Display comment input form
	 *
	 * @param array $tag_params
	 */
	private function showInputForm($tag_params) {
		$module = isset($tag_params['module']) ? fix_chars($tag_params['module']) : null;
		$section = isset($tag_params['section']) ? fix_chars($tag_params['section']) : null;

		if (is_null($module) || is_null($section)) return;

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('comment_input_form.xml', $this->path.'templates/');
		}
		$template->setMappedModule($this->name);

		$params = array(
					'module'		=> $module,
					'section'		=> $section,
					'size_limit'	=> $this->settings['size_limit'],
					'form_action'	=> url_Make('save_data', $this->name)
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Save comment data and send response as JSON Object
	 */
	private function saveCommentData() {
		if ($this->_canPostComment()) {
			$module = (isset($_REQUEST['module']) && !empty($_REQUEST['module'])) ? fix_chars($_REQUEST['module']) : null;
			$comment_section = (isset($_REQUEST['comment_section']) && !empty($_REQUEST['comment_section'])) ? fix_chars($_REQUEST['comment_section']) : null;
			$user = fix_chars($_REQUEST['user']);
			$email = fix_chars($_REQUEST['email']);

			$message = fix_chars($_REQUEST['comment']);
			if (strlen($message) > $this->settings['size_limit']) {
				$tmp = str_split($message, $this->settings['size_limit']);
				$message = $tmp[0];
			}

			if (!is_null($module) || !is_null($comment_section)) {
				$data = array(
							'module'	=> $module,
							'section'	=> $comment_section,
							'user'		=> $user,
							'email'		=> $email,
							'address'	=> isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
							'message'	=> $message,
							'visible'	=> $this->settings['default_visibility']
						);

				$manager = CommentManager::getInstance();
				$manager->insertData($data);

				$response_message = $this->getLanguageConstant('message_saved');
			} else {
				// invalide module and/or comment section
				$response_message = $this->getLanguageConstant('message_error');
			}
		} else {
			$response_message = str_replace(
												'%t',
												$this->settings['repost_time'],
												$this->getLanguageConstant('message_error_repost_time')
											);
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $reponse_message,
					'button'		=> $this->getLanguageConstant('close'),
					'action'		=> window_Close('comments_settings')
				);
		$params = array_merge($params, $this->settings);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();

	}

	/**
	 * Print JSON object containing all the comments
	 *
	 * @param boolean $only_visible
	 */
	private function printCommentData($only_visible = true) {
		$module = (isset($_REQUEST['module']) && !empty($_REQUEST['module'])) ? fix_chars($_REQUEST['module']) : null;
		$comment_section = (isset($_REQUEST['comment_section']) && !empty($_REQUEST['comment_section'])) ? fix_chars($_REQUEST['comment_section']) : null;

		$result = array();

		if (!is_null($module) || !is_null($comment_section)) {
			$result['error'] = 0;
			$result['error_message'] = '';

			$starting_with = isset($_REQUEST['starting_with']) ? fix_id($_REQUEST['starting_with']) : null;

			$manager = CommentManager::getInstance();
			$conditions = array(
							'module'	=> $module,
							'section'	=> $comment_section,
						);

			if (!is_null($starting_with))
				$conditions['id'] = array(
										'operator'	=> '>',
										'value'		=> $starting_with
									);

			if ($only_visible)
				$conditions['visible'] = 1;

			$items = $manager->getItems(array('id', 'user', 'message', 'timestamp'), $conditions);

			$result['last_id'] = 0;
			$result['comments'] = array();

			if (count($items) > 0) {
				foreach($items as $item) {
					$timestamp = strtotime($item->timestamp);
					$date = date($this->getLanguageConstant('format_date_short'), $timestamp);
					$time = date($this->getLanguageConstant('format_time_short'), $timestamp);

					$result['comments'][] = array(
												'id'		=> $item->id,
												'user'		=> empty($item->user) ? 'Anonymous' : $item->user,
												'content'	=> $item->message,
												'date'		=> $date,
												'time'		=> $time
											);
				}

				$result['last_id'] = end($items)->id;
			}
		} else {
			// no comments_section and/or module specified
			$result['error'] = 1;
			$result['error_message'] = $this->getLanguageConstant('message_error_data');
		}

		print json_encode($result);
	}

	/**
	 * Check if user can post comment. Users who are not logged are allowed one comment in specified period of time.
	 *
	 * @return boolean
	 */
	private function _canPostComment() {
		if (isset($_SECTION['logged']) && $_SECTION['logged']) {
			$result = true;
		} else {
			$manager = CommentManager::getInstance();
			$time = date('Y-m-d H:i:s', time() - (intval($this->settings['repost_time']) * 60));

			$count = $manager->sqlResult("
									SELECT count(id)
									FROM `comments`
									WHERE
										`address` = '{$_SERVER['REMOTE_ADDR']}' AND
										`timestamp` > '{$time}';"
								);

			$result = ($count == 0);
		}

		return $result;
	}
}


class CommentManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('comments');

		$this->addProperty('id', 'int');
		$this->addProperty('module', 'varchar');
		$this->addProperty('section', 'varchar');
		$this->addProperty('user', 'varchar');
		$this->addProperty('email', 'varchar');
		$this->addProperty('address', 'varchar');
		$this->addProperty('message', 'text');
		$this->addProperty('timestamp', 'timestamp');
		$this->addProperty('visible', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}

