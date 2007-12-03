--TEST--
PEAR2_Pyrus_Config::constructDefaults() extension_dir from PHP_PEAR_EXTENSION_DIR
--INI--
extension_dir=something
--ENV--
PATH=.
PHP_PEAR_EXTENSION_DIR=somethingelse
--FILE--
<?php
require dirname(__FILE__) . '/setup.php.inc';
tc::constructDefaults();
$defaults = tc::getTestDefaults();
$test->assertEquals('somethingelse', $defaults['ext_dir'], 'after');
?>
===DONE===
--EXPECT--
===DONE===
