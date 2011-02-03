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

class DarkConsoleErrorLogPluginAPI {

  private static $errors = array();

  public static function getErrors() {
    return self::$errors;
  }

  public static function handleError($num, $str, $file, $line, $cxt) {
    self::$errors[] = array(
      'event'   => 'error',
      'num'     => $num,
      'str'     => $str,
      'file'    => $file,
      'line'    => $line,
      'cxt'     => $cxt,
      'trace'   => debug_backtrace(),
    );
    error_log("{$file}:{$line} {$str}");
  }

  public static function handleException($ex) {
    self::$errors[] = array(
      'event'     => 'exception',
      'exception' => $ex,
    );
    error_log($ex);
  }

}
