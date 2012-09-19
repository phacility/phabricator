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

final class PhabricatorPolicyType extends PhabricatorPolicyConstants {

  const TYPE_GLOBAL       = 'global';
  const TYPE_PROJECT      = 'project';
  const TYPE_MASKED       = 'masked';

  public static function getPolicyTypeOrder($type) {
    static $map = array(
      self::TYPE_GLOBAL   => 0,
      self::TYPE_PROJECT  => 1,
      self::TYPE_MASKED   => 9,
    );
    return idx($map, $type, 9);
  }

  public static function getPolicyTypeName($type) {
    switch ($type) {
      case self::TYPE_GLOBAL:
        return pht('Global Policies');
      case self::TYPE_PROJECT:
        return pht('Members of Project');
      case self::TYPE_MASKED:
      default:
        return pht('Other Policies');
    }
  }

}
