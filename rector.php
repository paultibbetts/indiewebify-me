<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/config',
		__DIR__ . '/public',
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->withPhpSets(php85: true);
