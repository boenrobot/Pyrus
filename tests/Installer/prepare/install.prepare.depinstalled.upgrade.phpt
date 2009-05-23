--TEST--
PEAR2_Pyrus_Installer::prepare(), dependency is installed, but can be upgraded
--FILE--
<?php

define('MYDIR', __DIR__);
include __DIR__ . '/../setup.php.inc';
require __DIR__ . '/../../Mocks/Internet.php';

Internet::addDirectory(__DIR__ . '/../../Mocks/Internet/install.prepare.explicitstate',
                       'http://pear2.php.net/');
PEAR2_Pyrus_REST::$downloadClass = 'Internet';
PEAR2_Pyrus_Installer::$options['upgrade'] = true;
class b extends PEAR2_Pyrus_Installer
{
    static $installPackages = array();
}

// first, install P2 1.0.0 in the registry
$a = new PEAR2_Pyrus_PackageFile(__DIR__ .
                                '/../../Mocks/Internet/install.prepare.explicitstate/rest/r/p2/package.1.0.0.xml');
PEAR2_Pyrus_Config::current()->registry->package[] = $a->info;

b::begin();
b::prepare(new PEAR2_Pyrus_Package('pear2/P1-beta'));
b::preCommitDependencyResolve();
$test->assertEquals(2, count(b::$installPackages), '2 packages should be installed');
$test->assertEquals('1.1.0RC1', b::$installPackages['pear2.php.net/P1']->version['release'], 'verify P1-1.1.0RC1');
$test->assertEquals('1.1.0RC3', b::$installPackages['pear2.php.net/P2']->version['release'], 'verify P2-1.1.0RC3');
b::rollback();
?>
===DONE===
--CLEAN--
<?php
$dir = __DIR__ . '/testit';
include __DIR__ . '/../../clean.php.inc';
?>
--EXPECT--
===DONE===