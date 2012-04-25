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
