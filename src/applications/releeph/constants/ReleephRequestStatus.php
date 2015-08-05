<?php

final class ReleephRequestStatus extends Phobject {

  const STATUS_REQUESTED       = 1;
  const STATUS_NEEDS_PICK      = 2;  // aka approved
  const STATUS_REJECTED        = 3;
  const STATUS_ABANDONED       = 4;
  const STATUS_PICKED          = 5;
  const STATUS_REVERTED        = 6;
  const STATUS_NEEDS_REVERT    = 7;  // aka revert requested

  public static function getStatusDescriptionFor($status) {
    $descriptions = array(
      self::STATUS_REQUESTED       => pht('Requested'),
      self::STATUS_REJECTED        => pht('Rejected'),
      self::STATUS_ABANDONED       => pht('Abandoned'),
      self::STATUS_PICKED          => pht('Pulled'),
      self::STATUS_REVERTED        => pht('Reverted'),
      self::STATUS_NEEDS_PICK      => pht('Needs Pull'),
      self::STATUS_NEEDS_REVERT    => pht('Needs Revert'),
    );
    return idx($descriptions, $status, '??');
  }

  public static function getStatusClassSuffixFor($status) {
    $description = self::getStatusDescriptionFor($status);
    $class = str_replace(' ', '-', strtolower($description));
    return $class;
  }

}
