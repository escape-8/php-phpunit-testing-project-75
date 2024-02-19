test:
	composer exec --verbose phpunit tests

test-debug:
	composer exec --verbose phpunit -- --debug tests

autoload:
	composer dump-autoload