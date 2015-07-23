<?php

/**
 * Interface to the static resource map, which is a graph of available
 * resources, resource dependencies, and packaging information. You generally do
 * not need to invoke it directly; instead, you call higher-level Celerity APIs
 * and it uses the resource map to satisfy your requests.
 */
final class CelerityResourceMap extends Phobject {

  private static $instances = array();

  private $resources;
  private $symbolMap;
  private $requiresMap;
  private $packageMap;
  private $nameMap;
  private $hashMap;
  private $componentMap;

  public function __construct(CelerityResources $resources) {
    $this->resources = $resources;

    $map = $resources->loadMap();
    $this->symbolMap = idx($map, 'symbols', array());
    $this->requiresMap = idx($map, 'requires', array());
    $this->packageMap = idx($map, 'packages', array());
    $this->nameMap = idx($map, 'names', array());

    // We derive these reverse maps at runtime.

    $this->hashMap = array_flip($this->nameMap);
    $this->componentMap = array();
    foreach ($this->packageMap as $package_name => $symbols) {
      foreach ($symbols as $symbol) {
        $this->componentMap[$symbol] = $package_name;
      }
    }
  }

  public static function getNamedInstance($name) {
    if (empty(self::$instances[$name])) {
      $resources_list = CelerityPhysicalResources::getAll();
      if (empty($resources_list[$name])) {
        throw new Exception(
          pht(
            'No resource source exists with name "%s"!',
            $name));
      }

      $instance = new CelerityResourceMap($resources_list[$name]);
      self::$instances[$name] = $instance;
    }

    return self::$instances[$name];
  }

  public function getNameMap() {
    return $this->nameMap;
  }

  public function getSymbolMap() {
    return $this->symbolMap;
  }

  public function getRequiresMap() {
    return $this->requiresMap;
  }

  public function getPackageMap() {
    return $this->packageMap;
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
    if (empty($this->symbolMap[$symbol])) {
      throw new Exception(
        pht(
          'Attempting to resolve unknown resource, "%s".',
          $symbol));
    }

    $hash = $this->symbolMap[$symbol];

    $map[$symbol] = $hash;

    if (isset($this->requiresMap[$hash])) {
      $requires = $this->requiresMap[$hash];
    } else {
      $requires = array();
    }

    foreach ($requires as $required_symbol) {
      if (!empty($map[$required_symbol])) {
        continue;
      }
      $this->resolveResource($map, $required_symbol);
    }
  }

  private function packageResources(array $resolved_map) {
    $packaged = array();
    $handled = array();
    foreach ($resolved_map as $symbol => $hash) {
      if (isset($handled[$symbol])) {
        continue;
      }

      if (empty($this->componentMap[$symbol])) {
        $packaged[] = $this->hashMap[$hash];
      } else {
        $package_name = $this->componentMap[$symbol];
        $packaged[] = $package_name;

        $package_symbols = $this->packageMap[$package_name];
        foreach ($package_symbols as $package_symbol) {
          $handled[$package_symbol] = true;
        }
      }
    }

    return $packaged;
  }

  public function getResourceDataForName($resource_name) {
    return $this->resources->getResourceData($resource_name);
  }

  public function getResourceNamesForPackageName($package_name) {
    $package_symbols = idx($this->packageMap, $package_name);
    if (!$package_symbols) {
      return null;
    }

    $resource_names = array();
    foreach ($package_symbols as $symbol) {
      $resource_names[] = $this->hashMap[$this->symbolMap[$symbol]];
    }

    return $resource_names;
  }


  /**
   * Get the epoch timestamp of the last modification time of a symbol.
   *
   * @param string Resource symbol to lookup.
   * @return int Epoch timestamp of last resource modification.
   */
  public function getModifiedTimeForName($name) {
    if ($this->isPackageResource($name)) {
      $names = array();
      foreach ($this->packageMap[$name] as $symbol) {
        $names[] = $this->getResourceNameForSymbol($symbol);
      }
    } else {
      $names = array($name);
    }

    $mtime = 0;
    foreach ($names as $name) {
      $mtime = max($mtime, $this->resources->getResourceModifiedTime($name));
    }

    return $mtime;
  }


  /**
   * Return the absolute URI for the resource associated with a symbol. This
   * method is fairly low-level and ignores packaging.
   *
   * @param string Resource symbol to lookup.
   * @return string|null Resource URI, or null if the symbol is unknown.
   */
  public function getURIForSymbol($symbol) {
    $hash = idx($this->symbolMap, $symbol);
    return $this->getURIForHash($hash);
  }


  /**
   * Return the absolute URI for the resource associated with a resource name.
   * This method is fairly low-level and ignores packaging.
   *
   * @param string Resource name to lookup.
   * @return string|null  Resource URI, or null if the name is unknown.
   */
  public function getURIForName($name) {
    $hash = idx($this->nameMap, $name);
    return $this->getURIForHash($hash);
  }


  /**
   * Return the absolute URI for a resource, identified by hash.
   * This method is fairly low-level and ignores packaging.
   *
   * @param string Resource hash to lookup.
   * @return string|null Resource URI, or null if the hash is unknown.
   */
  private function getURIForHash($hash) {
    if ($hash === null) {
      return null;
    }
    return $this->resources->getResourceURI($hash, $this->hashMap[$hash]);
  }


  /**
   * Return the resource symbols required by a named resource.
   *
   * @param string Resource name to lookup.
   * @return list<string>|null  List of required symbols, or null if the name
   *                            is unknown.
   */
  public function getRequiredSymbolsForName($name) {
    $hash = idx($this->nameMap, $name);
    if ($hash === null) {
      return null;
    }
    return idx($this->requiresMap, $hash, array());
  }


  /**
   * Return the resource name for a given symbol.
   *
   * @param string Resource symbol to lookup.
   * @return string|null Resource name, or null if the symbol is unknown.
   */
  public function getResourceNameForSymbol($symbol) {
    $hash = idx($this->symbolMap, $symbol);
    return idx($this->hashMap, $hash);
  }

  public function isPackageResource($name) {
    return isset($this->packageMap[$name]);
  }

  public function getResourceTypeForName($name) {
    return $this->resources->getResourceType($name);
  }

}
