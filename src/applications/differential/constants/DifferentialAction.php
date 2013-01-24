<?php

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
  const ACTION_REOPEN         = 'reopen';

  public static function getActionPastTenseVerb($action) {
    $verbs = array(
      self::ACTION_COMMENT        => 'commented on',
      self::ACTION_ACCEPT         => 'accepted',
      self::ACTION_REJECT         => 'requested changes to',
      self::ACTION_RETHINK        => 'planned changes to',
      self::ACTION_ABANDON        => 'abandoned',
      self::ACTION_CLOSE          => 'closed',
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
      self::ACTION_REOPEN         => 'reopened',
    );

    if (!empty($verbs[$action])) {
      return $verbs[$action];
    } else {
      return 'brazenly "'.$action.'ed"';
    }
  }

  public static function getActionVerb($action) {
    $verbs = array(
      self::ACTION_COMMENT        => pht('Comment'),
      self::ACTION_ACCEPT         => pht("Accept Revision \xE2\x9C\x94"),
      self::ACTION_REJECT         => pht("Request Changes \xE2\x9C\x98"),
      self::ACTION_RETHINK        => pht("Plan Changes \xE2\x9C\x98"),
      self::ACTION_ABANDON        => pht('Abandon Revision'),
      self::ACTION_REQUEST        => pht('Request Review'),
      self::ACTION_RECLAIM        => pht('Reclaim Revision'),
      self::ACTION_RESIGN         => pht('Resign as Reviewer'),
      self::ACTION_ADDREVIEWERS   => pht('Add Reviewers'),
      self::ACTION_ADDCCS         => pht('Add CCs'),
      self::ACTION_CLOSE          => pht('Close Revision'),
      self::ACTION_CLAIM          => pht('Commandeer Revision'),
      self::ACTION_REOPEN         => pht('Reopen'),
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
