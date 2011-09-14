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
class DarkConsoleErrorLogPluginAPI {

  private static $errors = array();

  private static $discardMode = false;

  public static function enableDiscardMode() {
    self::$discardMode = true;
  }

  public static function getErrors() {
    return self::$errors;
  }

  public static function handleErrors($event, $value, $metadata) {
    if (self::$discardMode) {
      return;
    }

    switch ($event) {
      case PhutilErrorHandler::EXCEPTION:
        // $value is of type Exception
        self::$errors[] = array(
          'details'   => $value->getMessage(),
          'event'     => $event,
          'file'      => $value->getFile(),
          'line'      => $value->getLine(),
          'str'       => $value->getMessage(),
          'trace'     => $metadata['trace'],
        );
        break;
      case PhutilErrorHandler::ERROR:
        // $value is a simple string
        self::$errors[] = array(
          'details'   => $value,
          'event'     => $event,
          'file'      => $metadata['file'],
          'line'      => $metadata['line'],
          'str'       => $value,
          'trace'     => $metadata['trace'],
        );
        break;
      case PhutilErrorHandler::PHLOG:
        // $value can be anything
        self::$errors[] = array(
          'details' => PhutilReadableSerializer::printShallow($value, 3),
          'event'   => $event,
          'file'    => $metadata['file'],
          'line'    => $metadata['line'],
          'str'     => PhutilReadableSerializer::printShort($value),
          'trace'   => $metadata['trace'],
        );
        break;
      default:
        error_log('Unknown event : '.$event);
        break;
    }
  }

}

