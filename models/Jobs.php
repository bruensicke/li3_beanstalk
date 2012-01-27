<?php

namespace li3_beanstalk\models;

use lithium\data\Connections;
use li3_beanstalk\Socket_Beanstalk;

class Jobs extends \lithium\core\StaticObject {

	protected static $_queue;

	protected $_types = array(
		'command',
		'webhook',
		'modelhook'
	);

	/**
	 * Creates a new standard-Job, according to given type.
	 *
	 * @param string $type the type of Job to be created
	 * @param string $data necessary data for that Job, structure depends on type
	 * @param string $options additional options to be passed
	 * @return boolean true on success, false otherwise
	 */
	public function create($type, $data, $options = array()) {
		if(!in_array(strtolower($type), self::$_types)) {
			return false; // invalid type given, try to create a Job via Jobs::put
		}
	}

	/**
	 * Retrieve a list of Job Items
	 *
	 * @param string $type filter to to delayed, buried, ready or all
	 * @param string $options currently no $options are supported
	 * @return array a flat array with all Jobs, each Job is an array with
	 *               keys id, state, type and name
	 */
	public static function queue($type = 'all', $options = array()) {
		
		$defaults = array(
			'limit' => 1,
		);
		$options = array_merge($defaults, $options);

		$stats = self::statistics();

		$delayed = ($type == 'all' || $type == 'delayed')
			? self::top('delayed', $options['limit'])
			: array();

		$buried = ($type == 'all' || $type == 'buried')
			? self::top('delayed', $options['limit'])
			: array();

		$ready = ($type == 'all' || $type == 'ready')
			? self::top('delayed', $options['limit'])
			: array();

		return array_merge($delayed, $buried, $ready);
	}

	/**
	 * Retrieve all vital statistics for current queue
	 *
	 * @return array all meta-data for queue, according to 'stats' command
	 */
	public static function statistics() {
		$stats = self::stats();
		$lines = explode("\n", $stats);
		$result = array();
		foreach ($lines as $line) {
			if(!strpos($line, ':')) {
				continue;
			}
			list($key, $value) = explode(':', $line);
			$result[$key] = trim($value);
		}
		return $result;
	}

	/**
	 * Peek into Job-Queue
	 *
	 * @param string $type Type of Jobs to retrieve, delayed, buried or ready
	 * @param string $limit how many Jobs to retrieve
	 * @return array a flat array with all Jobs, each Job is an array with
	 *               keys id, state, type and name
	 */
	public static function top($type = 'ready', $limit = 50) {
		$result = array();
		for ($i=0; $i < $limit; $i++) {

			switch($type) {
				case 'delayed':
					$next = Jobs::peekDelayed();
					break;
				
				case 'buried':
					$next = Jobs::peekBuried();
					break;
				
				case 'ready':
				default:
					$next = Jobs::peekReady();
					break;
			}

			if(!$next) {
				continue;
			}
			$body = unserialize($next['body']);
			$result[] = array(
				'id' => $next['id'],
				'state' => 'delayed',
				'type' => $body['type'],
				'name' => $body['name'],
			);
		}
		return $result;
	}

	/**
	 * Returns new Socket_Beanstalk instance, creates one, if necessary
	 *
	 * @return object
	 */
	protected static function _queue() {
		if (static::$_queue) {
			return static::$_queue;
		}
		$config = Connections::get('queue', array('config' => true));

		$queue = new Socket_Beanstalk(array('host' => $config['host']));
		$queue->connect();

		return static::$_queue = $queue;
	}

	/**
	 * auxillary method to pass all calls directly to the beanstalk_queue object
	 *
	 * @param string $method $method to be called
	 * @param string $args all arguments as array
	 * @return mixed
	 */
	public static function __callStatic($method, $args) {
		if (!$queue = static::_queue()) {
			return false;
		}
		return call_user_func_array(array($queue, $method), $args);
	}
}
