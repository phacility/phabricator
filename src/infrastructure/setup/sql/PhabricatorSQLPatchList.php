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

final class PhabricatorSQLPatchList {

  public static function getPatchList() {
    $root = dirname(phutil_get_library_root('phabricator'));

    // Find the patch files
    $patches_dir = $root.'/resources/sql/patches/';
    $finder = id(new FileFinder($patches_dir))
      ->withSuffix('sql');
    $results = $finder->find();

    $patches = array();
    foreach ($results as $path) {
      $matches = array();
      if (preg_match('/(\d+)\..*\.sql$/', $path, $matches)) {
        $patches[] = array(
          'version' => (int)$matches[1],
          'path'    => $patches_dir.$path,
        );
      } else {
        throw new Exception("Patch file '{$path}' is not properly named.");
      }
    }

    // Files are in some 'random' order returned by the operating system
    // We need to apply them in proper order
    $patches = isort($patches, 'version');

    return $patches;
  }

  public static function getExpectedSchemaVersion() {
    $patches = self::getPatchList();
    $versions = ipull($patches, 'version');
    $max_version = max($versions);
    return $max_version;
  }

}
