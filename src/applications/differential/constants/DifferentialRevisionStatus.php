<?php

/**
 * NOTE: you probably want {@class:ArcanistDifferentialRevisionStatus}.
 * This class just contains a mapping for color within the Differential
 * application.
 */

final class DifferentialRevisionStatus {

  const COLOR_STATUS_DEFAULT = 'status';
  const COLOR_STATUS_DARK = 'status-dark';
  const COLOR_STATUS_GREEN = 'status-green';
  const COLOR_STATUS_RED = 'status-red';

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
        self::COLOR_STATUS_DARK,
    );
    return idx($map, $status, $default);
  }

  public static function getRevisionStatusIcon($status) {
    $default = 'oh-open';

    $map = array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW   =>
        'oh-open',
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION =>
        'oh-open-red',
      ArcanistDifferentialRevisionStatus::CHANGES_PLANNED =>
        'oh-open-red',
      ArcanistDifferentialRevisionStatus::ACCEPTED       =>
        'oh-open-green',
      ArcanistDifferentialRevisionStatus::CLOSED         =>
        'oh-closed-dark',
      ArcanistDifferentialRevisionStatus::ABANDONED      =>
        'oh-closed-dark',
      ArcanistDifferentialRevisionStatus::IN_PREPARATION =>
        'question-blue',
    );
    return idx($map, $status, $default);
  }

  public static function renderFullDescription($status) {
    $color = self::getRevisionStatusColor($status);
    $status_name =
      ArcanistDifferentialRevisionStatus::getNameForRevisionStatus($status);

    $img = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
      ->setSpriteIcon(self::getRevisionStatusIcon($status));

    $tag = phutil_tag(
      'span',
      array(
        'class' => 'phui-header-'.$color.' plr',
      ),
      array(
        $img,
        $status_name,
      ));

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
