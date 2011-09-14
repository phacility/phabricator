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
 * @group console
 */
final class DarkConsoleXHProfPluginAPI {

  private static $profilerStarted;

  public static function isProfilerAvailable() {
    return extension_loaded('xhprof');
  }

  public static function includeXHProfLib() {
    // TODO: this is incredibly stupid, but we may not have Phutil metamodule
    // stuff loaded yet so we can't just phutil_get_library_root() our way
    // to victory.
    $root = __FILE__;
    for ($ii = 0; $ii < 7; $ii++) {
      $root = dirname($root);
    }

    require_once $root.'/externals/xhprof/xhprof_lib.php';
  }

  public static function hookProfiler() {
    if (empty($_REQUEST['__profile__'])) {
      return;
    }

    if (!self::isProfilerAvailable()) {
      return;
    }

    if (self::$profilerStarted) {
      return;
    }

    self::startProfiler();
    self::$profilerStarted = true;
  }

  public static function startProfiler() {
    self::includeXHProfLib();
    // Note: HPHP's implementation of XHProf currently requires an argument
    // to xhprof_enable() -- see Facebook Task #531011.
    xhprof_enable(0);
  }

  public static function stopProfiler() {
    if (self::$profilerStarted) {
      $data = xhprof_disable();
      $data = serialize($data);
      $file_class = 'PhabricatorFile';
      PhutilSymbolLoader::loadClass($file_class);

      // Since these happen on GET we can't do guarded writes.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $file = call_user_func(
        array($file_class, 'newFromFileData'),
        $data,
        array(
          'mime-type' => 'application/xhprof',
          'name'      => 'profile.xhprof',
        ));
      return $file->getPHID();
    } else {
      return null;
    }
  }

}
