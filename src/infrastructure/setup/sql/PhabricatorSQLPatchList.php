<?php

/*
 * Copyright 2012 Facebook, Inc.
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
    $finder = new FileFinder($patches_dir);
    $results = $finder->find();

    $versions = array();
    $patches = array();
    foreach ($results as $path) {
      $matches = array();
      if (!preg_match('/(\d+)\..*\.(sql|php)$/', $path, $matches)) {
        continue;
      }
      $version = (int)$matches[1];
      $patches[] = array(
        'version' => $version,
        'path'    => $patches_dir.$path,
      );
      if (empty($versions[$version])) {
        $versions[$version] = true;
      } else {
        throw new Exception("Two patches have version {$version}!");
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
