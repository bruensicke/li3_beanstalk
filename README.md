# li3_beanstalk

Lithium library for connecting and working with a beanstalk queue, see [Beanstalkd](http://kr.github.com/beanstalkd/).

Beanstalk Spec version: `1.4.6`

## Installation

Add a submodule to your li3 libraries:

	git submodule add git@github.com:bruensicke/li3_beanstalk.git libraries/li3_beanstalk

and activate it in you app (config/bootstrap/libraries.php), of course:

	Libraries::add('li3_beanstalk');

## Todos

The following is my roadmap. If you need any of this features sooner than later, please let me know.

- Provide useful gui to manage queues
- setup connection / datasource for connection-details
- provide easy shell to peek into queues

## Credits

* [li3](http://www.lithify.me)
* [David Persson](https://github.com/nperson)

Please report any bug, here: https://github.com/bruensicke/li3_beanstalk/issues
