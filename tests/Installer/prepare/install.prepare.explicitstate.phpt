--TEST--
PEAR2_Pyrus_Installer::prepare(), installing package-beta with its dependencies
--FILE--
<?php
/**
 * Test cascade of preferred stability to a package and its dependencies only
 *
 * P1-1.1.0RC1 beta -> P2
 * P1-1.0.0         -> P2
 *
 * P2-1.2.0a1 alpha
 * P2-1.1.0RC3 beta
 * P2-1.0.0
 *
 * P3-1.1.0RC1 beta
 * P3-1.0.0
 *
 * Install of P1-beta and P3 should install
 *
 *  - P1-1.1.0RC1
 *  - P2-1.1.0RC3
 *  - P3-1.0.0
 */

define('MYDIR', __DIR__);
include __DIR__ . '/../setup.php.inc';
require __DIR__ . '/../../Mocks/Internet.php';

Internet::addDirectory(__DIR__ . '/../../Mocks/Internet/install.prepare.explicitstate',
                       'http://pear2.php.net/');
PEAR2_Pyrus_REST::$downloadClass = 'Internet';
class b extends PEAR2_Pyrus_Installer
{
    static $installPackages = array();
}

b::begin();
b::prepare(new PEAR2_Pyrus_Package('pear2/P1-beta'));
b::prepare(new PEAR2_Pyrus_Package('pear2/P3'));
b::preCommitDependencyResolve();
$test->assertEquals(3, count(b::$installPackages), '3 packages should be installed');
$pnames = array();
foreach (b::$installPackages as $package) {
    $pnames[] = $package->name;
    switch ($package->name) {
        case 'P1' :
            $test->assertEquals('1.1.0RC1', $package->version['release'], 'verify P1-1.1.0RC1');
            break;
        case 'P2' :
            $test->assertEquals('1.1.0RC3', $package->version['release'], 'verify P2-1.1.0RC3');
            break;
        case 'P3' :
            $test->assertEquals('1.0.0', $package->version['release'], 'verify P3-1.0.0');
            break;
        default:
            $test->assertEquals(false, $package->name, 'wrong package downloaded');
    }
}
sort($pnames);
$test->assertEquals(array('P1', 'P2', 'P3'), $pnames, 'correct packages');
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