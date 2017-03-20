<?php

final class DifferentialAction extends Phobject {

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

  public static function getBasicStoryText($action, $author_name) {
    switch ($action) {
      case self::ACTION_COMMENT:
        $title = pht(
          '%s commented on this revision.',
          $author_name);
        break;
      case self::ACTION_ACCEPT:
        $title = pht(
          '%s accepted this revision.',
          $author_name);
        break;
      case self::ACTION_REJECT:
        $title = pht(
          '%s requested changes to this revision.',
          $author_name);
        break;
      case self::ACTION_RETHINK:
        $title = pht(
          '%s planned changes to this revision.',
          $author_name);
        break;
      case self::ACTION_ABANDON:
        $title = pht(
          '%s abandoned this revision.',
          $author_name);
        break;
      case self::ACTION_CLOSE:
        $title = pht(
          '%s closed this revision.',
          $author_name);
        break;
      case self::ACTION_REQUEST:
        $title = pht(
          '%s requested a review of this revision.',
          $author_name);
        break;
      case self::ACTION_RECLAIM:
        $title = pht(
          '%s reclaimed this revision.',
          $author_name);
        break;
      case self::ACTION_UPDATE:
        $title = pht(
          '%s updated this revision.',
          $author_name);
        break;
      case self::ACTION_RESIGN:
        $title = pht(
          '%s resigned from this revision.',
          $author_name);
        break;
      case self::ACTION_SUMMARIZE:
        $title = pht(
          '%s summarized this revision.',
          $author_name);
        break;
      case self::ACTION_TESTPLAN:
        $title = pht(
          '%s explained the test plan for this revision.',
          $author_name);
        break;
      case self::ACTION_CREATE:
        $title = pht(
          '%s created this revision.',
          $author_name);
        break;
      case self::ACTION_ADDREVIEWERS:
        $title = pht(
          '%s added reviewers to this revision.',
          $author_name);
        break;
      case self::ACTION_ADDCCS:
        $title = pht(
          '%s added CCs to this revision.',
          $author_name);
        break;
      case self::ACTION_CLAIM:
        $title = pht(
          '%s commandeered this revision.',
          $author_name);
        break;
      case self::ACTION_REOPEN:
        $title = pht(
          '%s reopened this revision.',
          $author_name);
        break;
      case DifferentialTransaction::TYPE_INLINE:
        $title = pht(
          '%s added an inline comment.',
          $author_name);
        break;
      default:
        $title = pht('Ghosts happened to this revision.');
        break;
    }
    return $title;
  }

}
