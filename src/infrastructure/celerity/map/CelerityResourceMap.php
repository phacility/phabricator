<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class CelerityResourceMap {

  private static $instance;
  private $resourceMap;
  private $packageMap;

  public static function getInstance() {
    if (empty(self::$instance)) {
      self::$instance = new CelerityResourceMap();
      $root = phutil_get_library_root('phabricator');
      $ok = @include_once $root.'/__celerity_resource_map__.php';
      if (!$ok) {
        throw new Exception("Failed to load Celerity resource map!");
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
        "Attempting to resolve unknown resource, '{$symbol}'.");
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
        foreach ($package_info['symbols'] as $symbol) {
          $handled[$symbol] = true;
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

}

function celerity_register_resource_map(array $map, array $package_map) {
  $instance = CelerityResourceMap::getInstance();
  $instance->setResourceMap($map);
  $instance->setPackageMap($package_map);
}
