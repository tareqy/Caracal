<?php

/**
 * YouTube Implmenetation Module
 *
 * @author MeanEYE
 */

class youtube extends Module {

	/**
	 * Constructor
	 *
	 * @return youtube
	 */
	function youtube() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		switch ($params['action']) {
			default:
				break;
		}

		// global control actions
		switch ($params['backend_action']) {
			default:
				break;
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {
		global $db, $db_active;
		
		$sql = "
			CREATE TABLE IF NOT EXISTS `youtube_video` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`video_id` varchar(11) COLLATE utf8_bin NOT NULL,
				`title` varchar(255) COLLATE utf8_bin NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDeInit() {
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			//$group = new backend_MenuGroup("Blank", "", $this->name);
			//$group->addItem(new backend_MenuItem("Menu Item", "", "", 1));

			//$backend->addMenu($group);
		}
	}
}

class YouTube_VideoManager extends ItemManager {

	function QuestionManager() {
		parent::ItemManager('youtube_video');

		$this->addProperty('id', 'int');
		$this->addProperty('video_id', 'varchar');
		$this->addProperty('title', 'varchar');
	}
}

