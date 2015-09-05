<?php

final class PonderQuestionStatus extends PonderConstants {

  const STATUS_OPEN               = 'open';
  const STATUS_CLOSED_RESOLVED    = 'resolved';
  const STATUS_CLOSED_OBSOLETE    = 'obsolete';
  const STATUS_CLOSED_INVALID     = 'invalid';

  public static function getQuestionStatusMap() {
    return array(
      self::STATUS_OPEN              => pht('Open'),
      self::STATUS_CLOSED_RESOLVED   => pht('Closed, Resolved'),
      self::STATUS_CLOSED_OBSOLETE   => pht('Closed, Obsolete'),
      self::STATUS_CLOSED_INVALID    => pht('Closed, Invalid'),
    );
  }

  public static function getQuestionStatusFullName($status) {
    $map = array(
      self::STATUS_OPEN              => pht('Open'),
      self::STATUS_CLOSED_RESOLVED   => pht('Closed, Resolved'),
      self::STATUS_CLOSED_OBSOLETE   => pht('Closed, Obsolete'),
      self::STATUS_CLOSED_INVALID    => pht('Closed, Invalid'),
    );
    return idx($map, $status, pht('Unknown'));
  }

  public static function getQuestionStatusName($status) {
    $map = array(
      self::STATUS_OPEN              => pht('Open'),
      self::STATUS_CLOSED_RESOLVED   => pht('Resolved'),
      self::STATUS_CLOSED_OBSOLETE   => pht('Obsolete'),
      self::STATUS_CLOSED_INVALID    => pht('Invalid'),
    );
    return idx($map, $status, pht('Unknown'));
  }

  public static function getQuestionStatusDescription($status) {
    $map = array(
      self::STATUS_OPEN =>
        pht('This question is open for answers.'),
      self::STATUS_CLOSED_RESOLVED =>
        pht('This question has been answered or resolved.'),
      self::STATUS_CLOSED_OBSOLETE =>
        pht('This question is out of date.'),
      self::STATUS_CLOSED_INVALID =>
        pht('This question is invalid.'),
    );
    return idx($map, $status, pht('Unknown'));
  }

  public static function getQuestionStatusTagColor($status) {
    $map = array(
      self::STATUS_OPEN => PHUITagView::COLOR_BLUE,
      self::STATUS_CLOSED_RESOLVED => PHUITagView::COLOR_BLACK,
      self::STATUS_CLOSED_OBSOLETE => PHUITagView::COLOR_BLACK,
      self::STATUS_CLOSED_INVALID => PHUITagView::COLOR_BLACK,
    );

    return idx($map, $status);
  }

  public static function getQuestionStatusIcon($status) {
    $map = array(
      self::STATUS_OPEN => 'fa-question-circle',
      self::STATUS_CLOSED_RESOLVED => 'fa-check',
      self::STATUS_CLOSED_OBSOLETE => 'fa-ban',
      self::STATUS_CLOSED_INVALID => 'fa-ban',
    );

    return idx($map, $status);
  }

  public static function getQuestionStatusOpenMap() {
    return array(
      self::STATUS_OPEN,
    );
  }

  public static function getQuestionStatusClosedMap() {
    return array(
      self::STATUS_CLOSED_RESOLVED,
      self::STATUS_CLOSED_OBSOLETE,
      self::STATUS_CLOSED_INVALID,
    );
  }

}
