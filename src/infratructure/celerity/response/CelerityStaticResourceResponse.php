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

final class CelerityStaticResourceResponse {

  private $symbols = array();
  private $needsResolve = true;
  private $resolved;

  public function requireResource($symbol) {
    $this->symbols[$symbol] = true;
    $this->needsResolve = true;
    return $this;
  }

  private function resolveResources() {
    if ($this->needsResolve) {
      $map = CelerityResourceMap::getInstance();
      $this->resolved = $map->resolveResources(array_keys($this->symbols));
      $this->needsResolve = false;
    }
    return $this;
  }

  public function renderResourcesOfType($type) {
    $this->resolveResources();
    $output = array();
    foreach ($this->resolved as $resource) {
      if ($resource['type'] == $type) {
        $output[] = $this->renderResource($resource);
      }
    }
    return implode("\n", $output);
  }

  private function renderResource(array $resource) {
    switch ($resource['type']) {
      case 'css':
        $path = phutil_escape_html($resource['path']);
        return '<link rel="stylesheet" type="text/css" href="'.$path.'" />';
      case 'js':
        $path = phutil_escape_html($resource['path']);
        return '<script type="text/javascript" src="'.$path.'" />';
    }
    throw new Exception("Unable to render resource.");
  }

}
