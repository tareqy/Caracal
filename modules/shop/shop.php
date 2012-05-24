<?php

/**
 * Shop Module
 *
 * @author MeanEYE.rcf
 */

require_once('units/payment_method.php');
require_once('units/shop_item_handler.php');
require_once('units/shop_category_handler.php');
require_once('units/shop_currencies_handler.php');
require_once('units/shop_item_sizes_handler.php');
require_once('units/shop_item_size_values_manager.php');
require_once('units/shop_transactions_manager.php');
require_once('units/shop_transaction_items_manager.php');
require_once('units/shop_buyers_manager.php');
require_once('units/shop_buyer_addresses_manager.php');
require_once('units/shop_related_items_manager.php');


class TransactionType {
	const SUBSCRIPTION = 0;
	const SHOPPING_CART = 1;
	const DONATION = 2;
}


class TransactionStatus {
	const PENDING = 0;
	const DENIED = 1;
	const COMPLETED = 2;
}


class shop extends Module {
	private static $_instance;
	private $payment_methods;

	private $excluded_properties = array(
					'size_value', 'color_value', 'count'
				);

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $section;

		parent::__construct(__FILE__);

		// create payment providers container
		$this->payment_methods = array();

		// load module style and scripts
		if (class_exists('head_tag') && $section != 'backend') {
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/checkout.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/shopping_cart.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/shopping_cart.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend') && $section == 'backend') {
			$head_tag = head_tag::getInstance();
			$backend = backend::getInstance();

			if (class_exists('head_tag')) {
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/multiple_images.js'), 'type'=>'text/javascript'));
				$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/backend.js'), 'type'=>'text/javascript'));
			}

			$shop_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_shop'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					5  // level
				);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_items'),
								url_GetFromFilePath($this->path.'images/items.png'),
								window_Open( // on click open window
											'shop_items',
											580,
											$this->getLanguageConstant('title_manage_items'),
											true, true,
											backend_UrlMake($this->name, 'items')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_categories'),
								url_GetFromFilePath($this->path.'images/categories.png'),
								window_Open( // on click open window
											'shop_categories',
											490,
											$this->getLanguageConstant('title_manage_categories'),
											true, true,
											backend_UrlMake($this->name, 'categories')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_item_sizes'),
								url_GetFromFilePath($this->path.'images/item_sizes.png'),
								window_Open( // on click open window
											'shop_item_sizes',
											400,
											$this->getLanguageConstant('title_manage_item_sizes'),
											true, true,
											backend_UrlMake($this->name, 'sizes')
										),
								5  // level
							));
							
			$shop_menu->addSeparator(5);
						
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_special_offers'),
								url_GetFromFilePath($this->path.'images/special_offers.png'),
								window_Open( // on click open window
											'shop_special_offers',
											490,
											$this->getLanguageConstant('title_special_offers'),
											true, true,
											backend_UrlMake($this->name, 'special_offers')
										),
								5  // level
							));

			$shop_menu->addSeparator(5);

			// payment methods menu
			$methods_menu = new backend_MenuItem(
								$this->getLanguageConstant('menu_payment_methods'),
								url_GetFromFilePath($this->path.'images/payment_methods.png'),
								'javascript: void(0);', 5
							);

			$shop_menu->addChild('shop_payment_methods', $methods_menu);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_currencies'),
								url_GetFromFilePath($this->path.'images/currencies.png'),
								window_Open( // on click open window
											'shop_currencies',
											350,
											$this->getLanguageConstant('title_currencies'),
											true, true,
											backend_UrlMake($this->name, 'currencies')
										),
								5  // level
							));

			$shop_menu->addSeparator(5);

			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_purchases'),
								url_GetFromFilePath($this->path.'images/purchase.png'),
								window_Open( // on click open window
											'shop_purchases',
											490,
											$this->getLanguageConstant('title_purchases'),
											true, true,
											backend_UrlMake($this->name, 'purchases')
										),
								5  // level
							));
			$shop_menu->addChild(null, new backend_MenuItem(
								$this->getLanguageConstant('menu_stocks'),
								url_GetFromFilePath($this->path.'images/stock.png'),
								window_Open( // on click open window
											'shop_stocks',
											490,
											$this->getLanguageConstant('title_stocks'),
											true, true,
											backend_UrlMake($this->name, 'stocks')
										),
								5  // level
							));

			$backend->addMenu($this->name, $shop_menu);
		}

		// register search
		if (class_exists('search')) {
			$search = search::getInstance();
			$search->registerModule('shop', &$this);
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
	 * Get search results when asked by search module
	 * 
	 * @param array $query
	 * @param integer $threshold
	 * @return array
	 */
	public function getSearchResults($query, $threshold) {
		global $language;

		$manager = ShopItemManager::getInstance();
		$result = array();

		// get all items and process them
		$items = $manager->getItems(
								array(
									'id',
									'name', 
									'description'
								),
								array(
									'visible'	=> 1,
									'deleted'	=> 0,
								));

		// search through items
		if (count($items) > 0)
			foreach ($items as $item) {
				$title = strtolower($item->name[$language]);
				$description = strtolower($item->description[$language]);

				// get title score
				$title_score = levenshtein($query, $title);
				$title_length = mb_strlen($title);

				// get description score
				if (mb_strlen($description) <= 255) {
					$description_score = levenshtein($query, $description);
					$description_length = mb_strlen($description);

				} else {
					// text is bigger than our limit, make it short
					$chunks = array_chunk(preg_split("//u", $description, -1, PREG_SPLIT_NO_EMPTY), 255);
					$description_score = 255;

					// measure every chunk of description
					foreach ($chunks as $chunk) {
						$chunk_of_description = join('', $chunk);
						$current_score = levenshtein($query, $chunk_of_description);

						if ($current_score < $description_score) {
							$description_score = $current_score;
							$description_length = mb_strlen($chunk_of_description);
						}
					}
				}

				// calculate score
				if ($title_length > 0) {
					$title_score = $title_length - $title_score;
					if ($title_score < 0) $title_score = 0;
					$title_score = ((100 * $title_score) / $title_length);

				} else {
					$title_score = 0;
				}

				if ($description_length > 0) {
					$description_score = $description_length - $description_score;
					if ($description_score < 0) $description_score = 0;
					$description_score = ((100 * $description_score) / $description_length);

				} else {
					$description_score = 0;
				}

				// summarize score for this item
				$score = ($title_score * 0.8) + ($description_score * 0.2);

				// add item to result list
				if ($score >= $threshold)
					$result[] = array(
							'score'			=> $score,
							'title_score'	=> $title_score,
							'description_score'	=> $description_score,
							'title'			=> $item->name[$language],
							'description'	=> limit_words($item->description[$language], 200),
							'id'			=> $item->id,
							'type'			=> 'item',
							'module'		=> $this->name
						);
			}

		return $result;
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
				case 'show_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->tag_Item($params, $children);
					break;

				case 'show_item_list':
					$handler = ShopItemHandler::getInstance($this);
					$handler->tag_ItemList($params, $children);
					break;
					
				case 'show_category':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->tag_Category($params, $children);
					break;
					
				case 'show_category_list':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->tag_CategoryList($params, $children);
					break;

				case 'show_completed_message':
					$this->tag_CompletedMessage($params, $children);
					break;

				case 'show_canceled_message':
					$this->tag_CanceledMessage($params, $children);
					break;
					
				case 'checkout':
					$this->showCheckout();
					break;

				case 'checkout_completed':
					$this->showCheckoutCompleted();
					break;

				case 'checkout_canceled':
					$this->showCheckoutCanceled();
					break;

				case 'handle_payment':
					$this->handlePayment();
					break;
					
				case 'json_get_item':
					$handler = ShopItemHandler::getInstance($this);
					$handler->json_GetItem();
					break;

				case 'json_get_currency':
					$this->json_GetCurrency();
					break;

				case 'json_get_payment_methods':
					$this->json_GetPaymentMethods();
					break;

				case 'json_add_item_to_shopping_cart':
					$this->json_AddItemToCart();
					break;

				case 'json_remove_item_from_shopping_cart':
					$this->json_RemoveItemFromCart();
					break;

				case 'json_change_item_quantity':
					$this->json_ChangeItemQuantity();
					break;

				case 'json_clear_shopping_cart':
					$this->json_ClearCart();
					break;

				case 'json_get_shopping_cart':
					$this->json_ShowCart();
					break;

				case 'set_cart_from_template':
					$this->setCartFromTemplate($params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action'])) {
			$action = $params['backend_action'];

			switch ($action) {
				case 'items':
					$handler = ShopItemHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'currencies':
					$handler = ShopCurrenciesHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'categories':
					$handler = ShopCategoryHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;
					
				case 'sizes':
					$handler = ShopItemSizesHandler::getInstance($this);
					$handler->transferControl($params, $children);
					break;

				case 'special_offers':

				case 'purchases':

				case 'stocks':

				case 'payment_methods':

				default:
					break;
			}
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		// create shop items table
		$sql = "
			CREATE TABLE `shop_items` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`uid` VARCHAR(13) NOT NULL,";

		foreach($list as $language)
			$sql .= "`name_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`gallery` INT(11) NOT NULL,
				`size_definition` INT(11) NULL,
				`colors` VARCHAR(255) NOT NULL DEFAULT '',
				`author` INT(11) NOT NULL,
				`views` INT(11) NOT NULL,
				`price` DECIMAL(8,2) NOT NULL,
				`tax` DECIMAL(3,2) NOT NULL,
				`weight` DECIMAL(8,2) NOT NULL,
				`votes_up` INT(11) NOT NULL,
				`votes_down` INT(11) NOT NULL,
				`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`priority` INT(4) NOT NULL DEFAULT '5',
				`visible` BOOLEAN NOT NULL DEFAULT '1',
				`deleted` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` ),
				KEY `visible` (`visible`),
				KEY `deleted` (`deleted`),
				KEY `uid` (`uid`),
				KEY `author` (`author`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create shop currencies table
		$sql = "
			CREATE TABLE `shop_item_membership` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`category` INT(11) NOT NULL,
				`item` INT(11) NOT NULL,
				PRIMARY KEY ( `id` ),
				KEY `category` (`category`),
				KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create table for related shop items
		$sql = "
			CREATE TABLE IF NOT EXISTS `shop_related_items` (
				`item` int(11) NOT NULL,
				`related` int(11) NOT NULL,
				KEY `item` (`item`,`related`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		if ($db_active == 1) $db->query($sql);

		// create shop currencies tableshop_related_items
		$sql = "
			CREATE TABLE `shop_currencies` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`currency` VARCHAR(5) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop item sizes table
		$sql = "
			CREATE TABLE `shop_item_sizes` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(25) NOT NULL,
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop item size values table
		$sql = "
			CREATE TABLE `shop_item_size_values` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`definition` int(11) NOT NULL,";
				
		foreach($list as $language)
			$sql .= "`value_{$language}` VARCHAR( 50 ) NOT NULL DEFAULT '',";
			
		$sql .= "PRIMARY KEY ( `id` ),
				KEY `definition` (`definition`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);

		// create shop categories table
		$sql = "
			CREATE TABLE `shop_categories` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text_id` VARCHAR(32) NOT NULL,
				`parent` INT(11) NOT NULL DEFAULT '0',
				`image` INT(11),";

		foreach($list as $language)
			$sql .= "`title_{$language}` VARCHAR( 255 ) NOT NULL DEFAULT '',";

		foreach($list as $language)
			$sql .= "`description_{$language}` TEXT NOT NULL ,";

		$sql .="
				PRIMARY KEY ( `id` ),
				KEY `parent` (`parent`),
				KEY `text_id` (`text_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop buyers table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyers` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `first_name` varchar(64) NOT NULL,
				  `last_name` varchar(64) NOT NULL,
				  `email` varchar(127) NOT NULL,
				  `uid` varchar(50) NOT NULL,
				  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop buyer addresses table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_buyer_addresses` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `name` varchar(128) NOT NULL,
				  `street` varchar(200) NOT NULL,
				  `city` varchar(40) NOT NULL,
				  `zip` varchar(20) NOT NULL,
				  `state` varchar(40) NOT NULL,
				  `country` varchar(64) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop transactions table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transactions` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `buyer` int(11) NOT NULL,
				  `address` int(11) NOT NULL,
				  `uid` varchar(20) NOT NULL,
				  `type` smallint(6) NOT NULL,
				  `status` smallint(6) NOT NULL,
				  `currency` int(11) NOT NULL,
				  `handling` decimal(8,2) NOT NULL,
				  `shipping` decimal(8,2) NOT NULL,
				  `total` decimal(8,2) NOT NULL,
				  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  KEY `buyer` (`buyer`),
				  KEY `address` (`address`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";		
		if ($db_active == 1) $db->query($sql);
		
		// create shop transaction items table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_transaction_items` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `transaction` int(11) NOT NULL,
				  `item` int(11) NOT NULL,
				  `price` DECIMAL(8,2) NOT NULL,
				  `tax` DECIMAL(8,2) NOT NULL,
				  `amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `transaction` (`transaction`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
		// create shop stock table
		$sql = "CREATE TABLE IF NOT EXISTS `shop_stock` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `item` int(11) NOT NULL,
				  `size` int(11) DEFAULT NULL,
				  `amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `item` (`item`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=0;";
		if ($db_active == 1) $db->query($sql);
		
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$tables = array(
					'shop_items',
					'shop_currencies',
					'shop_categories',
					'shop_item_membership',
					'shop_item_sizes',
					'shop_item_size_values',
					'shop_buyers',
					'shop_buyer_addresses',
					'shop_transactions',
					'shop_transaction_items',
					'shop_stock',
					'shop_related_items'
				);
		
		$sql = "DROP TABLE IF EXISTS `".join('`, `', $tables)."`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Method used by payment providers to register them selfs
	 *
	 * @param string $name
	 * @param object $module
	 */
	public function registerPaymentMethod($name, $module) {
		$this->payment_methods[$name] = $module;
	}

	/**
	 * Generate variation Id based on UID and properties.
	 *
	 * @param string $uid
	 * @param array $properties
	 * @return string
	 */
	private function generateVariationId($uid, $properties) {
		$data = $uid;

		ksort($properties);
		foreach($properties as $key => $value)
			$data .= ",{$key}:{$value}";

		$result = md5($data);
		return $result;
	}

	/**
	 * Set content of a shopping cart from template
	 *
	 * @param 
	 */
	private function setCartFromTemplate($params, $children) {
		if (count($children) > 0) {
			$cart = array();
			$manager = ShopItemManager::getInstance();

			foreach ($children as $data) {
				$uid = array_key_exists('uid', $data->tagAttrs) ? fix_chars($data->tagAttrs['uid']) : null;
				$amount = array_key_exists('count', $data->tagAttrs) ? fix_id($data->tagAttrs['count']) : 0;
				$item = null;

				if (!is_null($uid))
					$item = $manager->getSingleItem(array('id'), array('uid' => $uid));

				// make sure item actually exists in database to avoid poluting
				if (is_object($item) && $amount > 0)
					$cart[$uid] = array(
								'uid'		=> $uid,
								'count'		=> $amount
							);
			}

			$_SESSION['shopping_cart'] = $cart;
		}
	}

	/**
	 * Show checkout form
	 */
	public function showCheckout() {
		$method_name = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		if (count($this->payment_methods) == 0)
			return;

		// get a payment method
		if (is_null($method_name) || !array_key_exists($method_name, $this->payment_methods)) {
			$methods = $this->payment_methods;
			$method = array_shift($methods);

		} else {
			$method = $this->payment_methods[$method_name];
		}

		$can_continue = true;

		// get buyer information if method doesn't provide one
		if (!$method->provides_information() && !isset($_SESSION['buyer'])) {
			$bad_fields = array();
			$message = "";

			// grab fields 
			if (isset($_POST['set_info']) && $_POST['set_info'] == 1) {
				$buyer = array();

				if (isset($_POST['first_name']) && !empty($_POST['first_name']))
					$buyer['first_name'] = fix_chars($_POST['first_name']); else
					$bad_fields[] = 'first_name';

				if (isset($_POST['last_name']) && !empty($_POST['last_name']))
					$buyer['last_name'] = fix_chars($_POST['last_name']); else
					$bad_fields[] = 'last_name';

				if (isset($_POST['email']) && !empty($_POST['email']))
					$buyer['email'] = fix_chars($_POST['email']); else
					$bad_fields[] = 'email';

				if (isset($_POST['name']) && !empty($_POST['name'])) {
					$buyer['name'] = fix_chars($_POST['name']); 
				} else if (isset($buyer['first_name']) && isset($buyer['last_name'])){
					$buyer['name'] = $buyer['first_name'].' '.$buyer['last_name'];
					$_POST['name'] = $buyer['name'];
				}

				if (isset($_POST['street']) && !empty($_POST['street']))
					$buyer['street'] = fix_chars($_POST['street']); else
					$bad_fields[] = 'street';

				if (isset($_POST['city']) && !empty($_POST['city']))
					$buyer['city'] = fix_chars($_POST['city']); else
					$bad_fields[] = 'city';

				if (isset($_POST['zip']) && !empty($_POST['zip']))
					$buyer['zip'] = fix_chars($_POST['zip']); else
					$bad_fields[] = 'zip';

				if (isset($_POST['country']) && !empty($_POST['country']))
					$buyer['country'] = fix_chars($_POST['country']); else
					$bad_fields[] = 'country';

				if (
					isset($buyer['country']) && $buyer['country'] == 'US' 
					&& (!isset($_POST['state']) || empty($_POST['state']))
				)
					$bad_fields[] = 'state'; else
					$buyer['state'] = fix_chars($_POST['state']); 
			}

			$can_continue = (count($bad_fields) == 0) && isset($_POST['set_info']);

			// store buyer to session
			if ($can_continue)
				$_SESSION['buyer'] = $buyer;
		}

		if (!$can_continue) {
			// create template
			$template = new TemplateHandler('buyer_information.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'message'		=> $message,
						'bad_fields'	=> $bad_fields
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();

		} else {
			// create template
			$template = new TemplateHandler('checkout.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'method'	=> $method->get_name()
					);

			// register tag handler
			$template->registerTagHandler('_checkout_form', &$this, 'tag_CheckoutForm');

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse();
		}
	}

	/**
	 * Show message for completed checkout and empty shopping cart
	 */
	private function showCheckoutCompleted() {
		$method_name = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		if (is_null($method_name) || !array_key_exists($method_name, $this->payment_methods))
			return;

		// get method and transaction id
		$method = $this->payment_methods[$method_name];
		$transactions_manager = ShopTransactionsManager::getInstance();

		$transaction_id = $method->get_transaction_id();
		$transaction = $transactions_manager->getSingleItem(array('id'), array('uid' => $transaction_id));

		// update transaction state
		if (is_object($transaction) && $method->verify_payment_complete()) {
			$transactions_manager->updateData(
									array('status' => TransactionStatus::COMPLETED),
									array(
										'uid' => $transaction_id,
										'status' => TransactionStatus::PENDING
									));
		}

		$template = new TemplateHandler('checkout_completed.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_completed_message', &$this, 'tag_CompletedMessage');

		$params = array();

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show message for canceled checkout
	 */
	private function showCheckoutCanceled() {
		$method_name = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		if (is_null($method_name) || !array_key_exists($method_name, $this->payment_methods))
			return;

		// get method and transaction id
		$method = $this->payment_methods[$method_name];
		$transactions_manager = ShopTransactionsManager::getInstance();

		$transaction_id = $method->get_transaction_id();
		$transaction = $transactions_manager->getSingleItem(array('id'), array('uid' => $transaction_id));

		// update transaction state
		if (is_object($transaction) && $method->verify_payment_canceled()) {
			$transactions_manager->updateData(
									array('status' => TransactionStatus::DENIED),
									array(
										'uid' => $transaction_id,
										'status' => TransactionStatus::PENDING
									));
		}

		$template = new TemplateHandler('checkout_canceled.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);
		$template->registerTagHandler('_canceled_message', &$this, 'tag_CanceledMessage');

		$params = array();

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle payment notification
	 */
	private function handlePayment() {
		$method_name = isset($_REQUEST['method']) ? fix_chars($_REQUEST['method']) : null;

		// return if no payment method is specified
		if (is_null($method_name) || !array_key_exists($method_name, $this->payment_methods))
			return;

		// verify payment
		$method = $this->payment_methods[$method_name];
		if ($method->verify_payment()) {
			// get data from payment method
			$payment_info = $method->get_payment_info();
			$transaction_info = $method->get_transaction_info();
			$buyer_info = $method->get_buyer_info();
			$items_info = $method->get_items();

			// get managers
			$items_manager = ShopItemManager::getInstance();
			$transactions_manager = ShopTransactionsManager::getInstance();
			$transaction_items_manager = ShopTransactionItemsManager::getInstance();
			$buyers_manager = ShopBuyersManager::getInstance();
			$buyer_addresses_manager = ShopBuyerAddressesManager::getInstance();
			$currencies_manager = ShopCurrenciesManager::getInstance();

			// try to get existing transaction
			$transaction = $transactions_manager->getSingleItem(
											array('id'),
											array('uid' => $transaction_info['id'])
										);

			if (is_object($transaction)) {
				// transaction already exists, we need to update status only
				$transactions_manager->updateData(
											array('status'	=> $transaction_info['status']),
											array('id'		=> $transaction->id)
										);

			} else {
				// transaction doesn't exist, try to get a buyer
				$buyer = $buyers_manager->getSingleItem(
												array('id'), 
												array('email' => $buyer_info['email'])
											);

				if (is_object($buyer)) {
					// buyer already exists in database
					$buyer_id = $buyer->id;

				} else {
					// new buyer, record data
					$data = $buyer_info;
					unset($data['address']);

					$buyers_manager->insertData($data);
					$buyer_id = $buyers_manager->getInsertedID();
				}

				// check if specified address for this buyer already exists
				$address = $buyer_addresses_manager->getSingleItem(
												array('id'),
												array(
													'buyer'	=> $buyer_id,
													'name'	=> $buyer_info['address']['name']
												)
											);

				if (is_object($address)) {
					// address is known to us, use it
					$address_id = $address->id;

				} else {
					// new address, record data
					$data = $buyer_info['address'];
					$data['buyer'] = $buyer_id;

					$buyer_addresses_manager->insertData($data);
					$address_id = $buyer_addresses_manager->getInsertedID();
				}

				// get currency based on code
				$currency = $currencies_manager->getSingleItem(
												array('id'),
												array('currency' => $payment_info['currency'])
											);

				if (is_object($currency))
					$currency_id = $currency->id; else
					$currency_id = 0;

				// record transaction and its items
				$data = array_merge($payment_info, $transaction_info);
				
				unset($data['id']);
				$data['address'] = $address_id;
				$data['buyer'] = $buyer_id;
				$data['currency'] = $currency_id;
				$data['uid'] = $transaction_info['id'];

				$transactions_manager->insertData($data);
				$transaction_id = $transactions_manager->getInsertedID();

				// store items
				foreach ($items_info as $item_info) {
					$item = $items_manager->getSingleItem(
												array('id'), 
												array('uid' => $item_info['uid'])
											);

					// only record items that are valid
					if (is_object($item)) {
						$data = array(
								'transaction'	=> $transaction_id,
								'item'			=> $item->id,
								'price'			=> $item_info['price'],
								'tax'			=> $item_info['tax'],
								'amount'		=> $item_info['quantity']
							);
						$transaction_items_manager->insertData($data);
					}
				}
			}
		}
	}

	/**
	 * Return default currency using JSON object
	 */
	private function json_GetCurrency() {
		print json_encode($this->getDefaultCurrency());
	}

	/**
	 * Show shopping card in form of JSON object
	 */
	private function json_ShowCart() {
		$manager = ShopItemManager::getInstance();
		$values_manager = ShopItemSizeValuesManager::getInstance();
		$gallery = class_exists('gallery') ? gallery::getInstance() : null;
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();

		$result = array();

		// get shopping cart from session
		$result['cart'] = array();
		$result['size_values'] = array();
		$result['count'] = count($result['cart']);
		$result['currency'] = $this->getDefaultCurrency();

		// organize shopping cart

		// colect ids from session
		$ids = array_keys($cart);

		// get items from database and prepare result
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$values = $values_manager->getItems($values_manager->getFieldNames(), array());

		if (count($items) > 0) 
			foreach ($items as $item) {
				// get item image url
				$thumbnail_url = !is_null($gallery) ? $gallery->getGroupThumbnailURL($item->gallery) : '';

				$uid = $item->uid;

				if (array_key_exists($uid, $cart) && count($cart[$uid]['variations']) > 0)
					foreach ($cart[$uid]['variations'] as $variation_id => $properties) {
						$new_properties = $properties;
						unset($new_properties['count']);

						$result['cart'][] = array(
									'name'			=> $item->name,
									'weight'		=> $item->weight,
									'price' 		=> $item->price,
									'tax'			=> $item->tax,
									'image'			=> $thumbnail_url,
									'uid'			=> $item->uid,
									'variation_id'	=> $variation_id,
									'count'			=> $properties['count'],
									'properties'	=> $new_properties
								);
					}
			}
		
		if (count($values) > 0) 
			foreach ($values as $value) {
				$result['size_values'][$value->id] = array(
											'definition'	=> $value->definition,
											'value'			=> $value->value
										);
			}
			
		print json_encode($result);
	}

	/**
	 * Clear shopping cart and return result in form of JSON object
	 */
	private function json_ClearCart() {
		$_SESSION['shopping_cart'] = array();

		print json_encode(true);
	}

	/**
	 * Add item to shopping cart using JSON request
	 */
	private function json_AddItemToCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$properties = isset($_REQUEST['properties']) ? fix_chars($_REQUEST['properties']) : array();
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$variation_id = $this->generateVariationId($uid, $properties);

		// try to get item from database
		$manager = ShopItemManager::getInstance();
		$item = $manager->getSingleItem($manager->getFieldNames(), array('uid' => $uid));

		// default result is false
		$result = null;

		if (is_object($item)) {
			if (array_key_exists($uid, $cart)) {
				// update existing item count
				$cart[$uid]['quantity']++;

			} else {
				// add new item to shopping cart
				$cart[$uid] = array(
							'uid'			=> $uid,
							'quantity'		=> 1,
							'variations'	=> array()
						);
			}

			if (!array_key_exists($variation_id, $cart[$uid]['variations'])) {
				$cart[$uid]['variations'][$variation_id] = $properties;
				$cart[$uid]['variations'][$variation_id]['count'] = 0;
			}

			// increase count in case it already exists
			$cart[$uid]['variations'][$variation_id]['count'] += 1;

			// get item image url
			$thumbnail_url = null;
			if (class_exists('gallery')) {
				$gallery = gallery::getInstance();
				$thumbnail_url = $gallery->getGroupThumbnailURL($item->gallery); 
			}

			// prepare result
			$result = array(
					'name' 			=> $item->name,
					'weight'		=> $item->weight,
					'price'			=> $item->price,
					'tax'			=> $item->tax,
					'image'			=> $thumbnail_url,
					'count'			=> $cart[$uid]['variations'][$variation_id]['count'],
					'variation_id'	=> $variation_id
				);

			// update shopping cart
			$_SESSION['shopping_cart'] = $cart;
		}

		print json_encode($result);
	}

	/**
	 * Remove item from shopping cart using JSON request
	 */
	private function json_RemoveItemFromCart() {
		$uid = fix_chars($_REQUEST['uid']);
		$variation_id = fix_chars($_REQUEST['variation_id']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart) && array_key_exists($variation_id, $cart[$uid]['variations'])) {
			$count = $cart[$uid]['variations'][$variation_id]['count'];
			unset($cart[$uid]['variations'][$variation_id]);

			$cart[$uid]['quantity'] -= $count;

			if (count($cart[$uid]['variations']) == 0)
				unset($cart[$uid]);

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		print json_encode($result);
	}

	private function json_ChangeItemQuantity() {
		$uid = fix_chars($_REQUEST['uid']);
		$variation_id = fix_chars($_REQUEST['variation_id']);
		$count = fix_id($_REQUEST['count']);
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$result = false;

		if (array_key_exists($uid, $cart) && array_key_exists($variation_id, $cart[$uid]['variations'])) {
			$old_count = $cart[$uid]['variations'][$variation_id]['count'];
			$cart[$uid]['variations'][$variation_id]['count'] = $count;

			$cart[$uid]['quantity'] += -$old_count + $count;

			$_SESSION['shopping_cart'] = $cart;
			$result = true;
		}

		print json_encode($result);
	}

	/**
	 * Retrieve list of payment methods
	 */
	private function json_GetPaymentMethods() {
		$result = array();

		// prepare data for printing
		foreach ($this->payment_methods as $payment_method)	
			$result[] = array(
					'name'	=> $payment_method->get_name(),
					'title'	=> $payment_method->get_title(),
					'icon'	=> $payment_method->get_icon_url()
				);

		// print data
		print json_encode($result);
	}

	/**
	 * Save default currency to module settings
	 * @param string $currency
	 */
	public function saveDefaultCurrency($currency) {
		$this->saveSetting('default_currency', $currency);
	}

	/**
	 * Return default currency
	 * @return string
	 */
	public function getDefaultCurrency() {
		return $this->settings['default_currency'];
	}

	/**
	 * Handle drawing checkout form
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutForm($tag_params, $children) {
		$method = null;

		// try to get specified payment method
		if (isset($tag_params['method']) && array_key_exists($tag_params['method'], $this->payment_methods))
			$method = $this->payment_methods[fix_chars($tag_params['method'])];

		// try to get fallback method
		if (is_null($method) && count($this->payment_methods) > 0) {
			$methods = $this->payment_methods;
			$method = array_shift($methods);
		}

		// we didn't manage to get any payment method, bail out
		if (is_null($method))
			return;

		// colect ids from session
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		if (count($cart) == 0)
			return;

		// get items from database and prepare result
		$manager = ShopItemManager::getInstance();
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));

		// prepare params
		$shipping = 0;
		$handling = 0;
		$total_money = 0;
		$total_weight = 0;
		$items_by_uid = array();
		$items_for_checkout = array();

		// parse items from database
		foreach ($items as $item) {
			$db_item = array(
					'name'		=> $item->name,
					'price'		=> $item->price,
					'tax'		=> $item->tax,
					'weight'	=> $item->weight
				);
			$items_by_uid[$item->uid] = $db_item;
		}

		// prepare items for checkout
		foreach ($cart as $uid => $item) {
			if (count($item['variations']) > 0)
				foreach($item['variations'] as $variation_id => $data) {
					// add items to checkout list
					$properties = $data;

					foreach ($this->excluded_properties as $key)
						if (isset($properties[$key]))
							unset($properties[$key]);

					$new_item = $items_by_uid[$uid];
					$new_item['count'] = $data['count'];
					$new_item['description'] = implode(', ', array_values($properties));

					// add item to the list
					$items_for_checkout[] = $new_item;

					// include item data in summary
					$tax = $new_item['tax'];
					$price = $new_item['price'];
					$weight = $new_item['weight'];
					
					$total_money += ($price * (1 + ($tax / 100))) * $data['count']; 
					$total_weight += $weight * $data['count'];
				}
		}

		// get fields for payment method
		$return_url = urlencode(url_Make('checkout_completed', 'shop', array('method', $method->get_name())));
		$cancel_url = urlencode(url_Make('checkout_canceled', 'shop', array('method', $method->get_name())));
		$transaction_data = array();

		// get currency info
		$currency = $this->settings['default_currency'];
		$currency_manager = ShopCurrenciesManager::getInstance();

		$currency_item = $currency_manager->getSingleItem(array('id'), array('currency' => $currency));

		if (is_object($currency_item))
			$transaction_data['currency'] = $currency_item->id;

		// if payment method doesn't provide us with buyer 
		// information, we might as well store it now
		if (!$method->provides_information()) {
			$data = $_SESSION['buyer'];
			$buyers_manager = ShopBuyersManager::getInstance();
			$address_manager = ShopBuyerAddressesManager::getInstance();

			// associate buyer with transaction
			$buyer = $buyers_manager->getSingleItem(
								array('id'), 
								array(
									'first_name'	=> $data['first_name'],
									'last_name'		=> $data['last_name'],
									'email'			=> $data['email']
								));

			if (is_object($buyer)) {
				// buyer already exists
				$transaction_data['buyer'] = $buyer->id;

			} else {
				// no buyer found, create new one
				$buyers_manager->insertData(array(
									'first_name'	=> $data['first_name'],
									'last_name'		=> $data['last_name'],
									'email'			=> $data['email']
								));
				$transaction_data['buyer'] = $buyers_manager->getInsertedID();
			}

			// associate address with transaction
			$address = $address_manager->getSingleItem(
								array('id'),
								array(
									'buyer'		=> $transaction_data['buyer'],
									'name'		=> $data['name'],
									'street'	=> $data['street'],
									'city'		=> $data['city'],
									'zip'		=> $data['zip'],
									'state'		=> $data['state'],
									'country'	=> $data['country'],
								));

			if (is_object($address)) {
				// existing address
				$transaction_data['address'] = $address_manager->getInsertedID();

			} else {
				// create new address
				$address_manager->insertData(array(
									'buyer'		=> $transaction_data['buyer'],
									'name'		=> $data['name'],
									'street'	=> $data['street'],
									'city'		=> $data['city'],
									'zip'		=> $data['zip'],
									'state'		=> $data['state'],
									'country'	=> $data['country'],
								));
				$transaction_data['address'] = $address_manager->getInsertedID();
			}
		}

		$transaction_data['uid'] = uniqid('', true);
		$transaction_data['type'] = TransactionType::SHOPPING_CART;
		$transaction_data['status'] = TransactionStatus::PENDING;
		$transaction_data['handling'] = $handling;
		$transaction_data['shipping'] = $shipping;
		$transaction_data['total'] = $total_money;

		// create new transaction
		$transactions_manager = ShopTransactionsManager::getInstance();
		$transactions_manager->insertData($transaction_data);

		// store items
		$transaction_items_manager = ShopTransactionItemsManager::getInstance();

		// create new payment
		$checkout_fields = $method->new_payment(
									$transaction_data,
									$items_for_checkout,
									$this->getDefaultCurrency(),
									$return_url,
									$cancel_url
								);

		// load template
		$template = $this->loadTemplate($tag_params, 'checkout_form.xml');
		$template->registerTagHandler('_checkout_items', &$this, 'tag_CheckoutItems');

		// parse template
		$params = array(
					'checkout_url'		=> $method->get_url(),
					'checkout_fields'	=> $checkout_fields,
					'checkout_name'		=> $method->get_title(),
					'sub-total'			=> number_format($total_money, 2),
					'shipping'			=> number_format($shipping, 2),
					'total_weight'		=> number_format($total_weight, 2),
					'total'				=> number_format($total_money + $shipping, 2),
					'currency'			=> $this->getDefaultCurrency()
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Handle drawing checkout items
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CheckoutItems($tag_params, $children) {
		global $language;

		$manager = ShopItemManager::getInstance();
		$cart = isset($_SESSION['shopping_cart']) ? $_SESSION['shopping_cart'] : array();
		$ids = array_keys($cart);

		// get items from database
		$items = $manager->getItems($manager->getFieldNames(), array('uid' => $ids));
		$items_by_uid = array();
		$items_for_checkout = array();

		// parse items from database
		foreach ($items as $item) {
			$db_item = array(
					'name'		=> $item->name,
					'price'		=> $item->price,
					'tax'		=> $item->tax,
					'weight'	=> $item->weight
				);
			$items_by_uid[$item->uid] = $db_item;
		}

		// prepare items for checkout
		foreach ($cart as $uid => $item) {
			if (count($item['variations']) > 0)
				foreach($item['variations'] as $variation_id => $data) {
					// add items to checkout list
					$properties = $data;

					foreach ($this->excluded_properties as $key)
						if (isset($properties[$key]))
							unset($properties[$key]);

					$new_item = $items_by_uid[$uid];
					$new_item['count'] = $data['count'];
					$new_item['description'] = implode(', ', array_values($properties));
					$new_item['total'] = number_format(($new_item['price'] * (1 + ($new_item['tax'] / 100))) * $new_item['count'], 2);
					$new_item['tax'] = number_format($new_item['price'], 2);
					$new_item['price'] = number_format($new_item['tax'], 2);
					$new_item['weight'] = number_format($new_item['weight'], 2);

					// add item to the list
					$items_for_checkout[] = $new_item;
				}
		}

		// load template
		$template = $this->loadTemplate($tag_params, 'checkout_form_item.xml');

		// parse template
		if (count($items_for_checkout) > 0)
			foreach ($items_for_checkout as $params) {
				$template->setLocalParams($params);
				$template->restoreXML();
				$template->parse();
			}
	}

	/**
	 * Show message for completed checkout operation
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CompletedMessage($tag_params, $children) {
		// kill shopping cart
		$_SESSION['shopping_cart'] = array();

		// show message
		$template = $this->loadTemplate($tag_params, 'checkout_message.xml');
		
		$params = array(
					'message'		=> $this->getLanguageConstant('message_checkout_completed'),
					'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
					'button_action'	=> url_Make('', 'home')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}

	/**
	 * Show message for canceled checkout operation
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_CanceledMessage($tag_params, $children) {
		// show message
		$template = $this->loadTemplate($tag_params, 'checkout_message.xml');
		
		$params = array(
					'message'		=> $this->getLanguageConstant('message_checkout_canceled'),
					'button_text'	=> $this->getLanguageConstant('button_take_me_back'),
					'button_action'	=> url_Make('', 'home')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse();
	}
}
