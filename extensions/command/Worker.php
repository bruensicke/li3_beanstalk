<?php

namespace li3_beanstalk\extensions\command;

use li3_beanstalk\models\Jobs;
use lithium\net\http\Service;
use lithium\analysis\Logger;

/**
 * This command acts as worker, continously retrieving jobs from the queue and
 * processing those.
 */
class Worker extends \lithium\console\Command {

	/**
	 * The name of the worker, used in log messages to distinguish
	 * between multiple workers.
	 *
	 * @var string
	 */
	public $name = 'worker';

	/**
	 * Be more verbose?
	 *
	 * @var boolean
	 */
	public $verbose = false;

	/**
	 * Register signals to exit gracefully.
	 *
	 * @var boolean
	 */
	public $signals = true;

	/**
	 * The job ID of the job currently processed.
	 *
	 * @var integer
	 */
	public $current_id;

	/**
	 * Main entry point for li3 commands
	 *
	 * @return void
	 */
	public function run() {
		$this->out("Worker `{$this->name}` is starting up...");

		if ($signals = $this->signals && extension_loaded('pcntl')) {
			$this->_installSignalHandlers();
		} else {
			// TODO: Jobs::bury($this->current_id);
			// register_shutdown_function();
		}

		while (true) {
			if ($signals) {
				$this->out('Dispatching signals.');
				pcntl_signal_dispatch();
			}
			$this->out('Waiting...');

			if ($data = Jobs::reserve()) {
				$id = $data['id'];
				$data = unserialize($data['body']);
			} else {
				$this->error('INVALID! Got invalid job (queue online?), waiting 5 seconds before I retry.');
				sleep(5);
				continue;
			}
			$this->current_id = $id;
			$this->out("FOUND - {$this->current_id} ", array('nl' => $this->verbose));

			if($this->verbose) {
				$this->out(var_export($data, true));
			}


			if(empty($data['type'])) {
				$this->error('- INVALID - no type set, waiting 5 seconds... ');
				sleep(5);
				continue;
			}

			$method = "type{$data['type']}";

			if(!is_callable(array($this, $method))) {
				$this->error('- INVALID - invalid type set, waiting 5 seconds... ');
				sleep(5);
				continue;
			}

			$this->out("- TYPE: {$data['type']} ", array('nl' => $this->verbose));

			if(!empty($data['name'])) {
				$this->out("\"{$data['name']}\" ", array('nl' => $this->verbose));
			}

			$success = $this->$method($data['body']);
			if (!$success) {
				$this->error('- FAILED! - burying job...');
				Jobs::bury($this->current_id);
				continue;
			}

			Jobs::delete($this->current_id);
			$this->out('- DONE!');
		}
	}

	/**
	 * Executes li3 shell command, with given $options
	 *
	 * NOT IMPLEMENTED, YET!
	 *
	 * @param array $data 
	 * @return boolean true on success, false otherwise
	 */
	public function typeCommand($data = array()) {

		$result = false;

		return $result;
	}

	/**
	 * Acts as Webhook Dispatcher
	 *
	 * NOT IMPLEMENTED, YET!
	 *
	 * @param array $data 
	 * @return boolean true on success, false otherwise
	 */
	public function typeWebhook($data = array()) {

		$result = false;

		return $result;
	}

	/**
	 * Fires callback on a Resultset (narrowed by $options) of given $model
	 *
	 * Method will call $model::find('all', $options) and $method on the result-set
	 *
	 * @param array $data 
	 * @return boolean true on success, false otherwise
	 */
	public function typeModelhook($data = array()) {
		extract($data);
		list($model, $method) = explode('::', $callback, 2);

		// fetch entities, for given conditions
		$entities = call_user_func_array(array($model, 'find'), array('all', $options));
		if($entities === false) {
			// No results, job can be buried
			$this->error('FAILED!, NO RESULT SET...');
			Jobs::bury($this->current_id);
			return false;
		}
		
		// call given method on resultset
		$result = call_user_func_array(array($entities, $method), $data);

		if (!$result) {
			return false;
		}
		return true;
	}

	/**
	 * Needed for installing signal Handlers, takes care of
	 * releasing jobs, if process gets terminated or interrupted.
	 *
	 * *NOTE:* needs pcntl extension
	 *
	 * @return void
	 */
	protected function _installSignalHandlers() {
		$this->out('Installing signal handlers...');

		$self = $this;
		$terminate = function($signal) use ($self) {
			$self->out('Terminating upon request...');

			if ($self->current_id) {
				$self->out("Releasing current job (with id `{$self->current_id}`)...");
				/* Highest priority, delay 10 seconds. */
				Jobs::release($self->current_id, 0, 10);
			}
			exit(0);
		};
		pcntl_signal(SIGINT, $terminate);
		pcntl_signal(SIGTERM, $terminate);
	}


	/* Overriden methods to log any messages. */

	public function out($output = null, $options = array('nl' => 1)) {
		Logger::debug("{$this->name} - {$output}");
		return parent::out($output, $options);
	}

	public function error($error = null, $options = array('nl' => 1)) {
		Logger::error("{$this->name} - {$error}");
		return parent::error($error, $options);
	}
}

