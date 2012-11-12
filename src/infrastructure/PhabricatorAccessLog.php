<?php

final class PhabricatorAccessLog {

  static $log;

  public static function init() {
    // NOTE: This currently has no effect, but some day we may reuse PHP
    // interpreters to run multiple requests. If we do, it has the effect of
    // throwing away the old log.
    self::$log = null;
  }

  public static function getLog() {
    if (!self::$log) {
      $path = PhabricatorEnv::getEnvConfig('log.access.path');
      $format = PhabricatorEnv::getEnvConfig('log.access.format');
      $format = nonempty(
        $format,
        "[%D]\t%p\t%h\t%r\t%u\t%C\t%m\t%U\t%R\t%c\t%T");

      if (!$path) {
        return null;
      }

      $log = new PhutilDeferredLog($path, $format);
      $log->setData(
        array(
          'D' => date('r'),
          'h' => php_uname('n'),
          'p' => getmypid(),
          'e' => time(),
        ));

      self::$log = $log;
    }

    return self::$log;
  }

}
