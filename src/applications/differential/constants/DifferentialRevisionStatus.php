<?php

/**
 * NOTE: you probably want {@class:ArcanistDifferentialRevisionStatus}.
 * This class just contains a mapping for color within the Differential
 * application.
 */

final class DifferentialRevisionStatus extends Phobject {

  const COLOR_STATUS_DEFAULT = 'bluegrey';
  const COLOR_STATUS_DARK = 'indigo';
  const COLOR_STATUS_BLUE = 'blue';
  const COLOR_STATUS_GREEN = 'green';
  const COLOR_STATUS_RED = 'red';

  public static function getRevisionStatusColor($status) {
    $default = self::COLOR_STATUS_DEFAULT;

    $map = array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW   =>
        self::COLOR_STATUS_DEFAULT,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION =>
        self::COLOR_STATUS_RED,
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED =>
        self::COLOR_STATUS_RED,
      ArcanistDifferentialRevisionStatus::ACCEPTED       =>
        self::COLOR_STATUS_GREEN,
      ArcanistDifferentialRevisionStatus::CLOSED         =>
        self::COLOR_STATUS_DARK,
      ArcanistDifferentialRevisionStatus::ABANDONED      =>
        self::COLOR_STATUS_DARK,
      ArcanistDifferentialRevisionStatus::IN_PREPARATION =>
        self::COLOR_STATUS_BLUE,
    );
    return idx($map, $status, $default);
  }

  public static function getRevisionStatusIcon($status) {
    $default = 'fa-square-o bluegrey';

    $map = array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW   =>
        'fa-square-o bluegrey',
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION =>
        'fa-refresh',
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED =>
        'fa-headphones',
      ArcanistDifferentialRevisionStatus::ACCEPTED       =>
        'fa-check',
      ArcanistDifferentialRevisionStatus::CLOSED         =>
        'fa-check-square-o',
      ArcanistDifferentialRevisionStatus::ABANDONED      =>
        'fa-plane',
      ArcanistDifferentialRevisionStatus::IN_PREPARATION =>
        'fa-question-circle',
    );
    return idx($map, $status, $default);
  }

  public static function renderFullDescription($status) {
    $status_name =
      ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);

    $tag = id(new PHUITagView())
      ->setName($status_name)
      ->setIcon(self::getRevisionStatusIcon($status))
      ->setShade(self::getRevisionStatusColor($status))
      ->setType(PHUITagView::TYPE_SHADE);

    return $tag;
  }

  public static function getClosedStatuses() {
    $statuses = array(
      ArcanistDifferentialRevisionStatus::CLOSED,
      ArcanistDifferentialRevisionStatus::ABANDONED,
    );

    if (PhabricatorEnv::getEnvConfig('differential.close-on-accept')) {
      $statuses[] = ArcanistDifferentialRevisionStatus::ACCEPTED;
    }

    return $statuses;
  }

  public static function getOpenStatuses() {
    return array_diff(self::getAllStatuses(), self::getClosedStatuses());
  }

  public static function getAllStatuses() {
    return array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED,
      ArcanistDifferentialRevisionStatus::ACCEPTED,
      ArcanistDifferentialRevisionStatus::CLOSED,
      ArcanistDifferentialRevisionStatus::ABANDONED,
      ArcanistDifferentialRevisionStatus::IN_PREPARATION,
    );
  }

  public static function isClosedStatus($status) {
    return in_array($status, self::getClosedStatuses());
  }

}
