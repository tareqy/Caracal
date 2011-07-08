<?php

/**
 * Shop Item Manager
 * 
 * This manager is used to manipulate data in shop_items table.
 * Don't try to access this table manually as other tables depend
 * on it (like payment logs and similar).
 * 
 * @author MeanEYE.rcf
 */

class ShopItemManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('shop_items');

		$this->addProperty('id', 'int');
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

?>