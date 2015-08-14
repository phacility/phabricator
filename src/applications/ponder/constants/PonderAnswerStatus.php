<?php

final class PonderAnswerStatus extends PonderConstants {

  const ANSWER_STATUS_VISIBLE     = 'visible';
  const ANSWER_STATUS_HIDDEN      = 'hidden';

  public static function getAnswerStatusMap() {
    return array(
      self::ANSWER_STATUS_VISIBLE  => pht('Visible'),
      self::ANSWER_STATUS_HIDDEN   => pht('Hidden'),
    );
  }

  public static function getAnswerStatusName($status) {
    $map = array(
      self::ANSWER_STATUS_VISIBLE  => pht('Visible'),
      self::ANSWER_STATUS_HIDDEN   => pht('Hidden'),
    );
    return idx($map, $status, pht('Unknown'));
  }


}
