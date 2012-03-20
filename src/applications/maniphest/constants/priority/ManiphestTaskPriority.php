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

/**
 * @group maniphest
 */
final class ManiphestTaskPriority extends ManiphestConstants {

  const PRIORITY_UNBREAK_NOW  = 100;
  const PRIORITY_TRIAGE       = 90;
  const PRIORITY_HIGH         = 80;
  const PRIORITY_NORMAL       = 50;
  const PRIORITY_LOW          = 25;
  const PRIORITY_WISH         = 0;

  public static function getTaskPriorityMap() {
    return array(
      self::PRIORITY_UNBREAK_NOW  => 'Unbreak Now!',
      self::PRIORITY_TRIAGE       => 'Needs Triage',
      self::PRIORITY_HIGH         => 'High',
      self::PRIORITY_NORMAL       => 'Normal',
      self::PRIORITY_LOW          => 'Low',
      self::PRIORITY_WISH         => 'Wishlist',
    );
  }

  public static function getTaskBriefPriorityMap() {
    return array(
      self::PRIORITY_UNBREAK_NOW  => 'Unbreak!',
      self::PRIORITY_TRIAGE       => 'Triage',
      self::PRIORITY_HIGH         => 'High',
      self::PRIORITY_NORMAL       => 'Normal',
      self::PRIORITY_LOW          => 'Low',
      self::PRIORITY_WISH         => 'Wish',
    );
  }


  public static function getLoadMap() {
    return array(
      self::PRIORITY_UNBREAK_NOW  => 16,
      self::PRIORITY_TRIAGE       => 8,
      self::PRIORITY_HIGH         => 4,
      self::PRIORITY_NORMAL       => 2,
      self::PRIORITY_LOW          => 1,
      self::PRIORITY_WISH         => 0,
    );
  }

  public static function getTaskPriorityName($priority) {
    return idx(self::getTaskPriorityMap(), $priority, '???');
  }
}
