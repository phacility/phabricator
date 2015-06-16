<?php

final class PonderQuestionStatus extends PonderConstants {

  const STATUS_OPEN     = 0;
  const STATUS_CLOSED   = 1;

  public static function getQuestionStatusMap() {
    return array(
      self::STATUS_OPEN     => pht('Open'),
      self::STATUS_CLOSED   => pht('Closed'),
    );
  }

  public static function getQuestionStatusFullName($status) {
    $map = array(
      self::STATUS_OPEN     => pht('Open'),
      self::STATUS_CLOSED   => pht('Closed by author'),
    );
    return idx($map, $status, pht('Unknown'));
  }

  public static function getQuestionStatusTagColor($status) {
    $map = array(
      self::STATUS_CLOSED => PHUITagView::COLOR_BLACK,
    );

    return idx($map, $status);
  }

}
