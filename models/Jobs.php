<?php

namespace li3_beanstalk\models;

use lithium\util\String;

class Jobs extends \lithium\core\StaticObject {

	/**
	 * a list of valid callback types
	 *
	 * @var array
	 */
	protected static $_types = array(
		'command',
		'webhook',
		'modelhook'
	);

	/**
	 * Class dependencies.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'socket' => 'li3_beanstalk\core\BeanstalkSocket',
		'connection' => 'lithium\data\Connections',
		'logger' => 'lithium\analysis\Logger'
	);

	/**
	 * holds connection to beanstalk
	 *
	 * @var object
	 */
	protected static $_queue;

	/**
	 * Creates a new standard-Job, according to given type.
	 *
	 * @param string $type the type of Job to be created
	 * @param string $name the type of Job to be created
	 * @param array $body necessary data for that Job, structure depends on type
	 * @param array $options additional options to be passed
	 *              - `'priority'` integer: Weight of priority, should be between
	 *                0 and 1024, defaults to 100
	 *              - `'delay'` integer: Seconds to wait before putting the job in the ready queue.
	 *                The job will be in the "delayed" state during this time, defaults to 0
	 *              - `'ttr'` integer: Time to run, number of seconds to allow a worker to run job.
	 *                The minimum ttr is 1, defaults to 60*60
	 * @return integer|boolean $job_id on success, false otherwise
	 */
	public static function create($type, $name, array $body = array(), array $options = array()) {
		if (!in_array(strtolower($type), self::$_types)) {
			return false; // invalid type given, try to create a Job via Jobs::put
		}
		$defaults = array(
			'priority' => 100,
			'delay' => 0,
			'ttr' => 60 * 60
		);
		$options += $defaults;
		$data = serialize(compact('type', 'name', 'body'));
		$result = Jobs::put($options['priority'], $options['delay'], $options['ttr'], $data);
		$logger = static::$_classes['logger'];
		if (!$result) {
			$logger::debug(sprintf("FAILED to create Job %s - %s", $type, $name));
			return false;
		}
		$logger::debug(sprintf("Created Job %s - %s", $type, $name));
		return $result;
	}

	/**
	 * Creates a modelhook, which is a delayed invocation of a model method
	 *
	 * @param object $entity Instance of current Model Record
	 * @param string $method name of method to be invoked
	 * @param array $params an array of parameters to be passed into the method
	 * @param array $options an array of options
	 * @return integer|boolean $job_id on success, false otherwise
	 */
	public static function modelhook($entity, $method, $params = array(), array $options = array()) {
		$defaults = array(
			'model' => $entity->model(),
			'name' => '{:method} on {:model} with id {:id}',
			'callback' => '{:model}::{:method}'
		);
		$options += $defaults;
		$options['name'] = String::insert($options['name'], array(
			'model' => $options['model'],
			'method' => $method,
			'id' => (string) $entity->{$entity->key()}
		));
		$options['callback'] = String::insert($options['callback'], array(
			'model' => $options['model'],
			'method' => $method,
			'id' => $entity->{$entity->key()}
		));
		$body = array(
			'callback' => $options['callback'],
			'options' => array(
				'conditions' => array($entity->key() => (string) $entity->{$entity->key()})
			),
			'data' => $params
		);
		return Jobs::create('Modelhook', $options['name'], $body, $options);
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
		$defaults = array('limit' => 50);
		$options += $defaults;

		$stats = self::statistics();

		$delayed = ($type == 'all' || $type == 'delayed')
			? self::top('delayed', $options['limit'])
			: array();

		$buried = ($type == 'all' || $type == 'buried')
			? self::top('buried', $options['limit'])
			: array();

		$ready = ($type == 'all' || $type == 'ready')
			? self::top('ready', $options['limit'])
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
			if (!strpos($line, ':')) {
				continue;
			}
			list($key, $value) = explode(':', $line);
			$result[$key] = trim($value);
		}
		if (empty($result)) {
			return false;
		}

		// additional fields
		$result['up-since'] = date(DATE_ATOM, strtotime("{$result['uptime']} seconds ago"));
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
		for ($i = 0; $i < $limit; $i++) {

			switch ($type) {
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

			if (!$next) {
				continue;
			}
			$body = unserialize($next['body']);
			$result[] = array(
				'id' => $next['id'],
				'state' => 'delayed',
				'type' => $body['type'],
				'name' => $body['name']
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
		$logger = static::$_classes['logger'];
		$connection = static::$_classes['connection'];
		$socket = static::$_classes['socket'];
		$config = $connection::get('queue', array('config' => true));
		$queue = new $socket(array('host' => $config['host']));
		$queue->connect();
		$logger::debug(sprintf('Established connection to beanstalk host: %s', $config['host']));
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

?>