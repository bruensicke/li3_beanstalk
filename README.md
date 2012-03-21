# li3_beanstalk

Lithium library for connecting and working with a beanstalk queue, see [Beanstalkd](http://kr.github.com/beanstalkd/).

Beanstalk Spec version: `1.4.6`

## Installation

Add a submodule to your li3 libraries:

	git submodule add git@github.com:bruensicke/li3_beanstalk.git libraries/li3_beanstalk

and activate it in you app (config/bootstrap/libraries.php), of course:

	Libraries::add('li3_beanstalk');

## Usage

Submit Jobs to the Queue using the `Job` Model. There are various types of Jobs.

- `Command` - Allows you to run li3 command shells
- `Webhook` - Allows you to call a custom url, including JSON payload
- `Modelhook` - Allows to call a model-method, for a set of results (with given options)

*Note: More types may be added in the future*


### Using the Jobs Model

Every Job submitted has the following structure:

#### Fields

| Name | Required | Description |
|------|:--------:|-------------|
|`type`| yes | indicates what type this job is, see above
|`name`| no | a description-field for your own notes
|`body`| yes | serialized array, must contain specific fields, according to type

### Easy method to delay methods called on the model:

	/**
	 * Handles dispatching of methods via beanstalkd
	 *
	 * @see li3_beanstalk\models\Jobs::create
	 * @param string $method The name of the method to call asynchronously.
	 * @param array $params The parameters to pass to method.
	 * @param array $options additional options, to be passed into Jobs::create
	 * @return integer|boolean Returns $job_id on success, false otherwise
	 */
	public function invoke_delayed($entity, $method, array $params = array(), array $options = array()) {
		if (!Libraries::get('li3_beanstalk')) {
			return;
		}
		$jobs = static::$_classes['jobs'];
		return $jobs::modelhook($entity, $method, $params, $options);
	}

In this case, you could call this method like that:

	$entity->invoke_delayed($methodname);

## Todos

The following is my roadmap. If you need any of this features sooner than later, please let me know.

- Provide useful gui to manage queues
- setup connection / datasource for connection-details
- provide easy shell to peek into queues

## Credits

* [li3](http://www.lithify.me)
* [David Persson](https://github.com/nperson)

Please report any bug, here: https://github.com/bruensicke/li3_beanstalk/issues
