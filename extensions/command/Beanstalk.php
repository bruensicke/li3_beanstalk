<?php

namespace li3_beanstalk\extensions\command;

use li3_beanstalk\models\Jobs;

use lithium\net\http\Service;
use lithium\analysis\Logger;

use lithium\console\command\Help;


/**
 * This command can interact with the Beanstalk Queue.
 */
class Beanstalk extends \lithium\console\Command {

	/**
	 * Main entry point for li3 commands
	 *
	 * @return void
	 */
	public function run() {
		return $this->_help();
	}

	/**
	 * Prints out statistics from beanstalk queue
	 *
	 * @return void
	 */
	public function stats() {
		$stats = Jobs::stats();
		$this->out($stats);
	}

}