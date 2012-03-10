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

/**
 * @task pathutil Path Utilities
 */
final class DiffusionPathIDQuery {

  public function __construct(array $paths) {
    $this->paths = $paths;
  }

  public function loadPathIDs() {
    $repository = new PhabricatorRepository();

    $path_normal_map = array();
    foreach ($this->paths as $path) {
      $normal = self::normalizePath($path);
      $path_normal_map[$normal][] = $path;
    }

    $paths = queryfx_all(
      $repository->establishConnection('r'),
      'SELECT * FROM %T WHERE pathHash IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array_map('md5', array_keys($path_normal_map)));
    $paths = ipull($paths, 'id', 'path');

    $result = array();

    foreach ($path_normal_map as $normal => $originals) {
      foreach ($originals as $original) {
        $result[$original] = idx($paths, $normal);
      }
    }

    return $result;
  }


  /**
   * Convert a path to the canonical, absolute representation used by Diffusion.
   *
   * @param string Some repository path.
   * @return string Canonicalized Diffusion path.
   * @task pathutil
   */
  public static function normalizePath($path) {

    // Normalize to single slashes, e.g. "///" => "/".
    $path = preg_replace('@[/]{2,}@', '/', $path);

    return '/'.trim($path, '/');
  }


  /**
   * Return the canonical parent directory for a path. Note, returns "/" when
   * passed "/".
   *
   * @param string Some repository path.
   * @return string That path's canonical parent directory.
   * @task pathutil
   */
  public static function getParentPath($path) {
    $path = self::normalizePath($path);
    return dirname($path);
  }


  /**
   * Generate a list of parents for a repository path. The path itself is
   * included.
   *
   * @param string Some repository path.
   * @return list List of canonical paths between the path and the root.
   * @task pathutil
   */
  public static function expandPathToRoot($path) {
    $path = self::normalizePath($path);
    $parents = array($path);
    $parts = explode('/', trim($path, '/'));
    while (count($parts) >= 1) {
      if (array_pop($parts)) {
        $parents[] = '/'.implode('/', $parts);
      }
    }
    return $parents;
  }

}
