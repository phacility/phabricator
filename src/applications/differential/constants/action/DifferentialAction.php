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

final class DifferentialAction {

  const ACTION_COMMIT         = 'commit';
  const ACTION_COMMENT        = 'none';
  const ACTION_ACCEPT         = 'accept';
  const ACTION_REJECT         = 'reject';
  const ACTION_ABANDON        = 'abandon';
  const ACTION_REQUEST        = 'request_review';
  const ACTION_RECLAIM        = 'reclaim';
  const ACTION_UPDATE         = 'update';
  const ACTION_RESIGN         = 'resign';
  const ACTION_SUMMARIZE      = 'summarize';
  const ACTION_TESTPLAN       = 'testplan';
  const ACTION_CREATE         = 'create';
  const ACTION_ADDREVIEWERS   = 'add_reviewers';

  public static function getActionVerb($action) {
    static $verbs = array(
      self::ACTION_COMMENT        => 'commented on',
      self::ACTION_ACCEPT         => 'accepted',
      self::ACTION_REJECT         => 'requested changes to',
      self::ACTION_ABANDON        => 'abandoned',
      self::ACTION_COMMIT         => 'committed',
      self::ACTION_REQUEST        => 'requested a review of',
      self::ACTION_RECLAIM        => 'reclaimed',
      self::ACTION_UPDATE         => 'updated',
      self::ACTION_RESIGN         => 'resigned from',
      self::ACTION_SUMMARIZE      => 'summarized',
      self::ACTION_TESTPLAN       => 'explained the test plan for',
      self::ACTION_CREATE         => 'created',
      self::ACTION_ADDREVIEWERS   => 'added reviewers to',
    );

    if (!empty($verbs[$action])) {
      return $verbs[$action];
    } else {
      return 'brazenly "'.$action.'ed"';
    }
  }

}
