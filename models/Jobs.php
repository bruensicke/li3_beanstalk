<?php

namespace li3_beanstalk\models;

use lithium\data\Connections;
use li3_beanstalk\Socket_Beanstalk;

class Jobs extends \lithium\core\StaticObject {

	protected static $_queue;

	protected static function _queue() {
		if (static::$_queue) {
			return static::$_queue;
		}
		$config = Connections::get('queue', array('config' => true));

		$queue = new Socket_Beanstalk(array('host' => $config['host']));
		$queue->connect();

		return static::$_queue = $queue;
	}

	public static function __callStatic($method, $args) {
		if (!$queue = static::_queue()) {
			return false;
		}
		return call_user_func_array(array($queue, $method), $args);
	}
}
