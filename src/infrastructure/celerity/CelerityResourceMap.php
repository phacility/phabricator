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

  public function resolveResources(array $symbols) {
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

  public function packageResources(array $resolved_map) {
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
    return $packaged;
  }

  public function resolvePackage($package_hash) {
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

  public function lookupSymbolInformation($symbol) {
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
   * Return the fully-qualified, absolute URI for the resource associated with
   * a resource name. This method is fairly low-level and ignores packaging.
   *
   * @param string Resource name to lookup.
   * @return string Fully-qualified resource URI.
   */
  public function getFullyQualifiedURIForName($name) {
    $info = $this->lookupFileInformation($name);
    if ($info) {
      return idx($info, 'uri');
    }
    return null;
  }


  /**
   * Return the resource symbols required by a named resource.
   *
   * @param string Resource name to lookup.
   * @return list<string> List of required symbols.
   */
  public function getRequiredSymbolsForName($name) {
    $info = $this->lookupFileInformation($name);
    if ($info) {
      return idx($info, 'requires', array());
    }
    return null;
  }


}
