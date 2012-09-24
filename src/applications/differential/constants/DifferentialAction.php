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

final class DifferentialAction {

  const ACTION_CLOSE          = 'commit';
  const ACTION_COMMENT        = 'none';
  const ACTION_ACCEPT         = 'accept';
  const ACTION_REJECT         = 'reject';
  const ACTION_RETHINK        = 'rethink';
  const ACTION_ABANDON        = 'abandon';
  const ACTION_REQUEST        = 'request_review';
  const ACTION_RECLAIM        = 'reclaim';
  const ACTION_UPDATE         = 'update';
  const ACTION_RESIGN         = 'resign';
  const ACTION_SUMMARIZE      = 'summarize';
  const ACTION_TESTPLAN       = 'testplan';
  const ACTION_CREATE         = 'create';
  const ACTION_ADDREVIEWERS   = 'add_reviewers';
  const ACTION_ADDCCS         = 'add_ccs';
  const ACTION_CLAIM          = 'claim';

  public static function getActionPastTenseVerb($action) {
    $verbs = array(
      self::ACTION_COMMENT        => 'commented on',
      self::ACTION_ACCEPT         => 'accepted',
      self::ACTION_REJECT         => 'requested changes to',
      self::ACTION_RETHINK        => 'planned changes to',
      self::ACTION_ABANDON        => 'abandoned',
      self::ACTION_CLOSE          => pht('closed'),
      self::ACTION_REQUEST        => 'requested a review of',
      self::ACTION_RECLAIM        => 'reclaimed',
      self::ACTION_UPDATE         => 'updated',
      self::ACTION_RESIGN         => 'resigned from',
      self::ACTION_SUMMARIZE      => 'summarized',
      self::ACTION_TESTPLAN       => 'explained the test plan for',
      self::ACTION_CREATE         => 'created',
      self::ACTION_ADDREVIEWERS   => 'added reviewers to',
      self::ACTION_ADDCCS         => 'added CCs to',
      self::ACTION_CLAIM          => 'commandeered',
    );

    if (!empty($verbs[$action])) {
      return $verbs[$action];
    } else {
      return 'brazenly "'.$action.'ed"';
    }
  }

  public static function getActionVerb($action) {
    static $verbs = array(
      self::ACTION_COMMENT        => 'Comment',
      self::ACTION_ACCEPT         => "Accept Revision \xE2\x9C\x94",
      self::ACTION_REJECT         => "Request Changes \xE2\x9C\x98",
      self::ACTION_RETHINK        => "Plan Changes \xE2\x9C\x98",
      self::ACTION_ABANDON        => 'Abandon Revision',
      self::ACTION_REQUEST        => 'Request Review',
      self::ACTION_RECLAIM        => 'Reclaim Revision',
      self::ACTION_RESIGN         => 'Resign as Reviewer',
      self::ACTION_ADDREVIEWERS   => 'Add Reviewers',
      self::ACTION_ADDCCS         => 'Add CCs',
      self::ACTION_CLOSE          => 'Close Revision',
      self::ACTION_CLAIM          => 'Commandeer Revision',
    );

    if (!empty($verbs[$action])) {
      return $verbs[$action];
    } else {
      return 'brazenly '.$action;
    }
  }

  public static function allowReviewers($action) {
    if ($action == DifferentialAction::ACTION_ADDREVIEWERS ||
        $action == DifferentialAction::ACTION_REQUEST) {
      return true;
    }
    return false;
  }

}
