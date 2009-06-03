<?php
/**
 * PEAR2_Pyrus_Registry_Xml
 *
 * PHP version 5
 *
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   SVN: $Id$
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */

/**
 * This is the central registry, that is used for all installer options,
 * stored in xml files
 *
 * It is designed for providing redundancy to the Sqlite3 registry and for
 * managing simple installation situations such as bundling a few packages
 * inside another application, or for distributing a registry with an
 * unzip-and-go application that can be used to construct an Sqlite3 registry.
 *
 * File conflict resolution can be done manually, via detectFileConflicts()
 * and is extremely slow, as each installed package must be processed in order
 * to determine the list of installed files.
 * 
 * @category  PEAR2
 * @package   PEAR2_Pyrus
 * @author    Greg Beaver <cellog@php.net>
 * @copyright 2008 The PEAR Group
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @link      http://svn.pear.php.net/wsvn/PEARSVN/Pyrus/
 */
class PEAR2_Pyrus_Registry_Xml extends PEAR2_Pyrus_Registry_Base
{
    protected $readonly;
    private $_path;

    function __construct($path, $readonly = false)
    {
        if (isset(PEAR2_Pyrus::$options['packagingroot'])) {
            $path = PEAR2_Pyrus::prepend(PEAR2_Pyrus::$options['packagingroot'], $path);
        }
        $this->_path = $path;
        $this->readonly = $readonly;
    }

    private function _nameRegistryPath(PEAR2_Pyrus_IPackageFile $info = null,
                                     $channel = null, $package = null, $version = null)
    {
        $channel = $info !== null ? $info->channel : $channel;
        $package = $info !== null ? $info->name : $package;
        $path = $this->_namePath($channel, $package);
        $version = $info !== null ? $info->version['release'] : $version;
        return $path . DIRECTORY_SEPARATOR . $version . '-package.xml';
    }

    private function _namePath($channel, $package)
    {
        return $this->_path . DIRECTORY_SEPARATOR .
            '.xmlregistry' . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR .
            str_replace('/', '!', $channel) .
            DIRECTORY_SEPARATOR . $package;
    }

    /**
     * Create the Channel!PackageName-Version-package.xml file
     *
     * @param PEAR2_Pyrus_IPackageFile $pf
     */
    function install(PEAR2_Pyrus_IPackageFile $info, $replace = false)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot install package, registry is read-only');
        }
        // remove previously installed version for upgrade
        $this->uninstall($info->name, $info->channel);
        $packagefile = $this->_nameRegistryPath($info);
        if (!@is_dir(dirname($packagefile))) {
            mkdir(dirname($packagefile), 0755, true);
        }

        if (!$replace) {
            $info->date = date('Y-m-d');
            $info->time = date('H:i:s');
        }
        foreach ($info->files as $name => $file) {
            unset($file->{'install-as'});
        }
        $arr = $info->toArray();
        file_put_contents($packagefile, (string) new PEAR2_Pyrus_XMLWriter($arr));
    }

    function uninstall($package, $channel)
    {
        if ($this->readonly) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot install package, registry is read-only');
        }
        if (!$this->exists($package, $channel)) {
            return;
        }
        $packagefile = glob($this->_namePath($channel, $package) .
            DIRECTORY_SEPARATOR . '*.xml');
        if (!$packagefile || !isset($packagefile[0])) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot find registry for package ' .
                $channel . '/' . $package);
        }
        unlink($packagefile[0]);
        rmdir(dirname($packagefile[0]));
    }

    public function exists($package, $channel)
    {
        $packagefile = $this->_namePath($channel, $package);
        return @file_exists($packagefile) && @is_dir($packagefile);
    }

    public function info($package, $channel, $field)
    {
        if (!$this->exists($package, $channel)) {
            throw new PEAR2_Pyrus_Registry_Exception('Unknown package ' . $channel .
                '/' . $package);
        }
        $packagefile = glob($this->_namePath($channel, $package) .
            DIRECTORY_SEPARATOR . '*.xml');
        if (!$packagefile || !isset($packagefile[0])) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot find registry for package ' .
                $channel . '/' . $package);
        }
        
        $packageobject = new PEAR2_Pyrus_Package($packagefile[0]);

        if ($field === null) {
            return $packageobject->getInternalPackage()->getPackageFile()->getPackageFileObject();
        }

        if ($field == 'version') {
            $field = 'release-version';
        } elseif ($field == 'installedfiles') {
            $ret = array();
            try {
                $config = new PEAR2_Pyrus_Config_Snapshot($packageobject->date . ' ' . $packageobject->time);
            } catch (Exception $e) {
                throw new PEAR2_Pyrus_Registry_Exception('Cannot retrieve files, config ' .
                                        'snapshot could not be processed', $e);
            }
            $roles = array();
            foreach (PEAR2_Pyrus_Installer_Role::getValidRoles($packageobject->getPackageType()) as $role) {
                // set up a list of file role => configuration variable
                // for storing in the registry
                $roles[$role] =
                    PEAR2_Pyrus_Installer_Role::factory($packageobject->getPackageType(), $role);
            }
            $ret = array();
            foreach ($packageobject->installcontents as $file) {
                $relativepath = $roles[$file->role]->getRelativeLocation($packageobject, $file);
                if (!$relativepath) {
                    continue;
                }
                $filepath = $config->{$roles[$file->role]->getLocationConfig()} .
                    DIRECTORY_SEPARATOR . $relativepath;
                $attrs = $file->getArrayCopy();
                $ret[$filepath] = $attrs['attribs'];
                $ret[$filepath]['installed_as'] = $filepath;
                $ret[$filepath]['relativepath'] = $relativepath;
                $ret[$filepath]['configpath'] = $config->{$roles[$file->role]->getLocationConfig()};
            }
            return $ret;
        } elseif ($field == 'dirtree') {
            $files = $this->info($package, $channel, 'installedfiles');
            foreach ($files as $file => $unused) {
                do {
                    $file = dirname($file);
                    if (strlen($file) > strlen($this->_path)) {
                        $ret[$file] = 1;
                    }
                } while (strlen($file) > strlen($this->_path));
            }
            $ret = array_keys($ret);
            usort($ret, 'strnatcasecmp');
            return array_reverse($ret);
        }

        return $packageobject->$field;
    }

    public function listPackages($channel)
    {
        $dir = $this->_namePath($channel, '');
        if (!@file_exists($dir)) {
            return array();
        }
        $ret = array();
        try {
            $parser = new PEAR2_Pyrus_XMLParser;
            foreach (new DirectoryIterator($dir) as $file) {
                if ($file->isDot()) {
                    continue;
                }
                try {
                    foreach (new DirectoryIterator($file->getPathName()) as $registries) {
                        if ($registries->isDir()) {
                            continue;
                        }
                        $a = $parser->parse($registries->getPathName());
                        $ret[] = $a['package']['name'];
                    }
                } catch (Exception $e) {
                    PEAR2_Pyrus_Log::log(0, 'Warning: corrupted XML registry entry: ' .
                        $file->getPathName() . ': ' . $e);
                }
            }
        } catch (Exception $e) {
            throw new PEAR2_Pyrus_Registry_Exception('Could not open channel directory for ' .
                'channel ' . $channel, $e);
        }
        return $ret;
    }

    public function toPackageFile($package, $channel)
    {
        if (!$this->exists($package, $channel)) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot retrieve package file object ' .
                'for package ' . $channel . '/' . $package . ', it is not installed');
        }
        $packagefile = $this->info($package, $channel, null);
        
        return $packagefile;
    }

    public function __get($var)
    {
        if ($var == 'package') {
            return new PEAR2_Pyrus_Registry_Xml_Package($this);
        }
    }

    /**
     * This is EXTREMELY inefficient, and should only be used
     * if an Sqlite3 registry is unavailable
     */
    public function getDependentPackages(PEAR2_Pyrus_IPackageFile $package)
    {
        // first construct a list of all installed packages
        $all = array();
        $config = PEAR2_Pyrus_Config::current();
        foreach ($config->channelregistry as $channel) {
            foreach ($this->listPackages($channel->name) as $packagename) {
                $all[] = $this->package[$channel->name . '/' . $packagename];
            }
        }

        $ret = array();
        // now scan them to see which packages depend on this one
        foreach ($all as $test) {
            if ($test->isEqual($package)) {
                continue;
            }
            if ($test->dependsOn($package)) {
                $ret[] = $test;
            }
        }
        return $ret;
    }

    /**
     * Detect any files already installed that would be overwritten by
     * files inside the package represented by $package
     */
    public function detectFileConflicts(PEAR2_Pyrus_IPackageFile $package)
    {
        // construct list of all installed files
        $allfiles = array();
        $filesByPackage = array();
        $config = PEAR2_Pyrus_Config::current();
        foreach ($config->channelregistry as $channel) {
            foreach ($this->listPackages($channel->name) as $packagename) {
                $files = $this->info($packagename, $channel->name, 'installedfiles');
                $newfiles = array();
                foreach ($files as $file) {
                    $newfiles[$file['installed_as']] = $file;
                }
                $filesByPackage[$channel->name . '/' . $packagename] = $newfiles;
                $allfiles = array_merge($allfiles, $newfiles);
            }
        }

        // now iterate over each file in the package, and note all the conflicts
        $roles = array();
        foreach (PEAR2_Pyrus_Installer_Role::getValidRoles($package->getPackageType()) as $role) {
            // set up a list of file role => configuration variable
            // for storing in the registry
            $roles[$role] =
                PEAR2_Pyrus_Installer_Role::factory($package->getPackageType(), $role);
        }
        $ret = array();
        foreach ($package->installcontents as $file) {
            $relativepath = $roles[$file->role]->getRelativeLocation($package, $file);
            if (!$relativepath) {
                continue;
            }
            $testpath = $config->{$roles[$file->role]->getLocationConfig()} .
                    DIRECTORY_SEPARATOR . $relativepath;
            if (isset($allfiles[$testpath])) {
                foreach ($filesByPackage as $pname => $files) {
                    if (isset($files[$testpath])) {
                        $ret[] = array($relativepath => $pname);
                        break;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Returns a list of registries present in the PEAR installation at $path
     * @param string
     * @return array
     */
    static public function detectRegistries($path)
    {
        if (isset(PEAR2_Pyrus::$options['packagingroot'])) {
            $path = PEAR2_Pyrus::prepend(PEAR2_Pyrus::$options['packagingroot'], $path);
        }
        if (file_exists($path . '/.xmlregistry') || is_dir($path . '/.xmlregistry')) {
            return array('Xml');
        }
        return array();
    }

    /**
     * Completely remove all traces of an xml registry
     */
    static public function removeRegistry($path)
    {
        if (!file_exists($path . '/.xmlregistry')) {
            return;
        }
        try {
            PEAR2_Pyrus_AtomicFileTransaction::rmrf(realpath($path . DIRECTORY_SEPARATOR . '.xmlregistry'));
        } catch (PEAR2_Pyrus_AtomicFileTransaction_Exception $e) {
            throw new PEAR2_Pyrus_Registry_Exception('Cannot remove XML registry: ' . $e->getMessage(), $e);
        }
    }
}
