<?php

/**
 * @phutil-external-symbol function xhprof_enable
 * @phutil-external-symbol function xhprof_disable
 */
final class DarkConsoleXHProfPluginAPI extends Phobject {

  private static $profilerStarted;
  private static $profilerRunning;
  private static $profileFilePHID;

  public static function isProfilerAvailable() {
    return extension_loaded('xhprof');
  }

  public static function getProfilerHeader() {
    return 'X-Phabricator-Profiler';
  }

  public static function isProfilerRequested() {
    if (!empty($_REQUEST['__profile__'])) {
      return $_REQUEST['__profile__'];
    }

    $header = AphrontRequest::getHTTPHeader(self::getProfilerHeader());
    if ($header) {
      return $header;
    }

    return false;
  }

  private static function shouldStartProfiler() {
    if (self::isProfilerRequested()) {
      return true;
    }

    static $sample_request = null;

    if ($sample_request === null) {
      if (PhabricatorEnv::getEnvConfig('debug.profile-rate')) {
        $rate = PhabricatorEnv::getEnvConfig('debug.profile-rate');
        if (mt_rand(1, $rate) == 1) {
          $sample_request = true;
        } else {
          $sample_request = false;
        }
      }
    }

    return $sample_request;
  }

  public static function isProfilerStarted() {
    return self::$profilerStarted;
  }

  private static function isProfilerRunning() {
    return self::$profilerRunning;
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


  public static function saveProfilerSample(PhutilDeferredLog $access_log) {
    $file_phid = self::getProfileFilePHID();
    if (!$file_phid) {
      return;
    }

    if (self::isProfilerRequested()) {
      $sample_rate = 0;
    } else {
      $sample_rate = PhabricatorEnv::getEnvConfig('debug.profile-rate');
    }

    $profile_sample = id(new PhabricatorXHProfSample())
      ->setFilePHID($file_phid)
      ->setSampleRate($sample_rate)
      ->setUsTotal($access_log->getData('T'))
      ->setHostname($access_log->getData('h'))
      ->setRequestPath($access_log->getData('U'))
      ->setController($access_log->getData('C'))
      ->setUserPHID($access_log->getData('P'));

    AphrontWriteGuard::allowDangerousUnguardedWrites(true);
      $caught = null;
      try {
        $profile_sample->save();
      } catch (Exception $ex) {
        $caught = $ex;
      }
    AphrontWriteGuard::allowDangerousUnguardedWrites(false);

    if ($caught) {
      throw $caught;
    }
  }

  public static function hookProfiler() {
    if (!self::shouldStartProfiler()) {
      return;
    }

    if (!self::isProfilerAvailable()) {
      return;
    }

    if (self::$profilerStarted) {
      return;
    }

    self::startProfiler();
  }

  private static function startProfiler() {
    self::includeXHProfLib();
    xhprof_enable();

    self::$profilerStarted = true;
    self::$profilerRunning = true;
  }

  public static function getProfileFilePHID() {
    self::stopProfiler();
    return self::$profileFilePHID;
  }

  private static function stopProfiler() {
    if (!self::isProfilerRunning()) {
      return;
    }

    $data = xhprof_disable();
    $data = @json_encode($data);
    self::$profilerRunning = false;

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
        array('PhabricatorFile', 'newFromFileData'),
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
    }

    self::$profileFilePHID = $file->getPHID();
  }

}
