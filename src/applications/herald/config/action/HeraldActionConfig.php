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

class HeraldActionConfig {

  const ACTION_ADD_CC       = 'addcc';
  const ACTION_REMOVE_CC    = 'remcc';
  const ACTION_EMAIL        = 'email';
  const ACTION_NOTHING      = 'nothing';

  public static function getActionMap() {
    return array(
      self::ACTION_ADD_CC       => 'Add emails to CC',
      self::ACTION_REMOVE_CC    => 'Remove emails from CC',
      self::ACTION_EMAIL        => 'Send an email to',
      self::ACTION_NOTHING      => 'Do nothing',
    );
  }

  public static function getActionMapForContentType($type) {
    $map = self::getActionMap();
    switch ($type) {
      case HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL:
        return array_select_keys(
          $map,
          array(
            self::ACTION_ADD_CC,
            self::ACTION_REMOVE_CC,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_COMMIT:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_MERGE:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_OWNERS:
        return array_select_keys(
          $map,
          array(
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ));
      default:
        throw new Exception("Unknown content type '{$type}'.");
    }
  }

}
