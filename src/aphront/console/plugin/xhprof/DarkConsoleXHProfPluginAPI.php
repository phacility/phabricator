<?php

/**
 * @group console
 * @phutil-external-symbol function xhprof_enable
 * @phutil-external-symbol function xhprof_disable
 */
final class DarkConsoleXHProfPluginAPI {

  private static $profilerStarted;

  public static function isProfilerAvailable() {
    return extension_loaded('xhprof');
  }

  public static function isProfilerRequested() {
    if (!empty($_REQUEST['__profile__'])) {
      return $_REQUEST['__profile__'];
    }

    static $profilerRequested = null;

    if (!isset($profilerRequested)) {
      if (PhabricatorEnv::getEnvConfig('debug.profile-rate')) {
        $rate = PhabricatorEnv::getEnvConfig('debug.profile-rate');
        if (mt_rand(1, $rate) == 1) {
          $profilerRequested = true;
        } else {
          $profilerRequested = false;
        }
      }
    }

    return $profilerRequested;
  }

  public static function includeXHProfLib() {
    // TODO: this is incredibly stupid, but we may not have Phutil metamodule
    // stuff loaded yet so we can't just phutil_get_library_root() our way
    // to victory.
    $root = __FILE__;
    for ($ii = 0; $ii < 6; $ii++) {
      $root = dirname($root);
    }

    require_once $root.'/externals/xhprof/xhprof_lib.php';
  }


  public static function hookProfiler() {
    if (!self::isProfilerRequested()) {
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
    xhprof_enable();
  }

  public static function stopProfiler() {
    if (self::$profilerStarted) {
      $data = xhprof_disable();
      $data = serialize($data);
      $file_class = 'PhabricatorFile';

      // Since these happen on GET we can't do guarded writes. These also
      // sometimes happen after we've disposed of the write guard; in this
      // case we need to disable the whole mechanism.

      $use_scope = AphrontWriteGuard::isGuardActive();
      if ($use_scope) {
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      } else {
        AphrontWriteGuard::allowDangerousUnguardedWrites(true);
      }

      $caught = null;
      try {
        $file = call_user_func(
          array($file_class, 'newFromFileData'),
          $data,
          array(
            'mime-type' => 'application/xhprof',
            'name'      => 'profile.xhprof',
          ));
      } catch (Exception $ex) {
        $caught = $ex;
      }

      if ($use_scope) {
        unset($unguarded);
      } else {
        AphrontWriteGuard::allowDangerousUnguardedWrites(false);
      }

      if ($caught) {
        throw $caught;
      } else {
        return $file->getPHID();
      }
    } else {
      return null;
    }
  }

}
