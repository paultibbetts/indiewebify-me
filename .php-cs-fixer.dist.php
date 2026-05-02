<?php

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/config',
		__DIR__ . '/public',
		__DIR__ . '/src',
		__DIR__ . '/tests',
	]);

return (new PhpCsFixer\Config())
	->setRiskyAllowed(false)
	->setRules([
		'@PSR12' => true,
		'array_syntax' => ['syntax' => 'short'],
		'single_quote' => true,
		'trailing_comma_in_multiline' => true,
	])
	->setFinder($finder);
