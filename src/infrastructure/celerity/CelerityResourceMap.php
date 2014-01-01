<?php

/**
 * Interface to the static resource map, which is a graph of available
 * resources, resource dependencies, and packaging information. You generally do
 * not need to invoke it directly; instead, you call higher-level Celerity APIs
 * and it uses the resource map to satisfy your requests.
 *
 * @group celerity
 */
final class CelerityResourceMap {

  private static $instance;
  private $resourceMap;
  private $packageMap;
  private $reverseMap;

  public static function getInstance() {
    if (empty(self::$instance)) {
      self::$instance = new CelerityResourceMap();
      $root = phutil_get_library_root('phabricator');

      $path = '__celerity_resource_map__.php';
      $ok = include_once $root.'/'.$path;
      if (!$ok) {
        throw new Exception(
          "Failed to load Celerity resource map!");
      }
    }
    return self::$instance;
  }

  public function setResourceMap($resource_map) {
    $this->resourceMap = $resource_map;
    return $this;
  }

  public function getPackagedNamesForSymbols(array $symbols) {
    $resolved = $this->resolveResources($symbols);
    return $this->packageResources($resolved);
  }

  private function resolveResources(array $symbols) {
    $map = array();
    foreach ($symbols as $symbol) {
      if (!empty($map[$symbol])) {
        continue;
      }
      $this->resolveResource($map, $symbol);
    }

    return $map;
  }

  private function resolveResource(array &$map, $symbol) {
    if (empty($this->resourceMap[$symbol])) {
      throw new Exception(
        "Attempting to resolve unknown Celerity resource, '{$symbol}'.");
    }

    $info = $this->resourceMap[$symbol];
    foreach ($info['requires'] as $requires) {
      if (!empty($map[$requires])) {
        continue;
      }
      $this->resolveResource($map, $requires);
    }

    $map[$symbol] = $info;
  }

  public function setPackageMap($package_map) {
    $this->packageMap = $package_map;
    return $this;
  }

  private function packageResources(array $resolved_map) {
    $packaged = array();
    $handled = array();
    foreach ($resolved_map as $symbol => $info) {
      if (isset($handled[$symbol])) {
        continue;
      }
      if (empty($this->packageMap['reverse'][$symbol])) {
        $packaged[$symbol] = $info;
      } else {
        $package = $this->packageMap['reverse'][$symbol];
        $package_info = $this->packageMap['packages'][$package];
        $packaged[$package_info['name']] = $package_info;
        foreach ($package_info['symbols'] as $packaged_symbol) {
          $handled[$packaged_symbol] = true;
        }
      }
    }

    $names = array();
    foreach ($packaged as $key => $resource) {
      if (isset($resource['disk'])) {
        $names[] = $resource['disk'];
      } else {
        $names[] = $key;
      }
    }

    return $names;
  }

  public function getResourceDataForName($resource_name) {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root).'/webroot/';
    return Filesystem::readFile($root.$resource_name);
  }

  public function getResourceNamesForPackageHash($package_hash) {
    $package = idx($this->packageMap['packages'], $package_hash);
    if (!$package) {
      return null;
    }

    $paths = array();
    foreach ($package['symbols'] as $symbol) {
      $paths[] = $this->resourceMap[$symbol]['disk'];
    }

    return $paths;
  }

  private function lookupSymbolInformation($symbol) {
    return idx($this->resourceMap, $symbol);
  }

  private function lookupFileInformation($path) {
    if (empty($this->reverseMap)) {
      $this->reverseMap = array();
      foreach ($this->resourceMap as $symbol => $data) {
        $data['provides'] = $symbol;
        $this->reverseMap[$data['disk']] = $data;
      }
    }
    return idx($this->reverseMap, $path);
  }


  /**
   * Get the epoch timestamp of the last modification time of a symbol.
   *
   * @param string Resource symbol to lookup.
   * @return int Epoch timestamp of last resource modification.
   */
  public function getModifiedTimeForName($name) {
    $package_hash = null;
    foreach ($this->packageMap['packages'] as $hash => $package) {
      if ($package['name'] == $name) {
        $package_hash = $hash;
        break;
      }
    }

    $root = dirname(phutil_get_library_root('phabricator')).'/webroot';

    $mtime = 0;

    if ($package_hash) {
      $names = $this->getResourceNamesForPackageHash($package_hash);
      foreach ($names as $component_name) {
        $info = $this->lookupFileInformation($component_name);
        if ($info) {
          $mtime = max($mtime, (int)filemtime($root.$info['disk']));
        }
      }
    } else {
      $info = $this->lookupFileInformation($name);
      if ($info) {
        $root = dirname(phutil_get_library_root('phabricator')).'/webroot';
        $mtime = (int)filemtime($root.$info['disk']);
      }
    }

    return $mtime;
  }


  /**
   * Return the absolute URI for the resource associated with a symbol. This
   * method is fairly low-level and ignores packaging.
   *
   * @param string Resource symbol to lookup.
   * @return string|null  Fully-qualified resource URI, or null if the symbol
   *                      is unknown.
   */
  public function getURIForSymbol($symbol) {
    $info = $this->lookupSymbolInformation($symbol);
    if ($info) {
      return idx($info, 'uri');
    }
    return null;
  }


  /**
   * Return the absolute URI for the resource associated with a resource name.
   * This method is fairly low-level and ignores packaging.
   *
   * @param string Resource name to lookup.
   * @return string|null  Fully-qualified resource URI, or null if the name
   *                      is unknown.
   */
  public function getURIForName($name) {
    $info = $this->lookupFileInformation($name);
    if ($info) {
      return idx($info, 'uri');
    }

    foreach ($this->packageMap['packages'] as $hash => $package) {
      if ($package['name'] == $name) {
        return $package['uri'];
      }
    }

    return null;
  }


  /**
   * Return the resource symbols required by a named resource.
   *
   * @param string Resource name to lookup.
   * @return list<string>|null  List of required symbols, or null if the name
   *                            is unknown.
   */
  public function getRequiredSymbolsForName($name) {
    $info = $this->lookupFileInformation($name);
    if ($info) {
      return idx($info, 'requires', array());
    }
    return null;
  }


  /**
   * Return the resource name for a given symbol.
   *
   * @param string Resource symbol to lookup.
   * @return string|null Resource name, or null if the symbol is unknown.
   */
  public function getResourceNameForSymbol($symbol) {
    $info = $this->lookupSymbolInformation($symbol);
    if ($info) {
      return idx($info, 'disk');
    }
    return null;
  }


}
