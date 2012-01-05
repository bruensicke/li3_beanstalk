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


You can use the Jobs model like this:

	use li3_beanstalk\models\Jobs;

	if (!$result = Jobs::put(10, 0, 60 * 60, serialize($data))) {
		throw new ErrorException('Failed to queue job.');
	}

	SourceRuns::applyFilter('save', function($self, $params, $chain) {
		if ($params['data']) {
			$params['entity']->set($params['data']);
			$params['data'] = array();
		}
		if (!$params['entity']->exists()) {
			// TODO: create Job
			Job::
			debug($params);
			exit;
		}
		return $chain->next($self, $params, $chain);
	});


## Todos

The following is my roadmap. If you need any of this features sooner than later, please let me know.

- Provide useful gui to manage queues
- setup connection / datasource for connection-details
- provide easy shell to peek into queues

## Credits

* [li3](http://www.lithify.me)
* [David Persson](https://github.com/nperson)

Please report any bug, here: https://github.com/bruensicke/li3_beanstalk/issues
