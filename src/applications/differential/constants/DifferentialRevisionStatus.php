<?php

/**
 * NOTE: you probably want {@class:ArcanistDifferentialRevisionStatus}.
 * This class just contains a mapping for color within the Differential
 * application.
 */

final class DifferentialRevisionStatus {

  public static function getRevisionStatusTagColor($status) {
    $default = PhabricatorTagView::COLOR_GREY;

    $map = array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW   =>
        PhabricatorTagView::COLOR_ORANGE,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION =>
        PhabricatorTagView::COLOR_RED,
      ArcanistDifferentialRevisionStatus::ACCEPTED       =>
        PhabricatorTagView::COLOR_GREEN,
      ArcanistDifferentialRevisionStatus::CLOSED         =>
        PhabricatorTagView::COLOR_BLUE,
      ArcanistDifferentialRevisionStatus::ABANDONED      =>
        PhabricatorTagView::COLOR_BLACK,
    );
    return idx($map, $status, $default);
  }
}
