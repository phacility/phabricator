<?php

final class DifferentialUnitFieldSpecification {

  public function renderWarningBoxForRevisionAccept() {
    $diff = $this->getDiff();
    $unit_warning = null;
    if ($diff->getUnitStatus() >= DifferentialUnitStatus::UNIT_WARN) {
      $titles =
        array(
          DifferentialUnitStatus::UNIT_WARN => 'Unit Tests Warning',
          DifferentialUnitStatus::UNIT_FAIL => 'Unit Tests Failure',
          DifferentialUnitStatus::UNIT_SKIP => 'Unit Tests Skipped',
          DifferentialUnitStatus::UNIT_POSTPONED => 'Unit Tests Postponed'
        );
      if ($diff->getUnitStatus() == DifferentialUnitStatus::UNIT_POSTPONED) {
        $content =
          "This diff has postponed unit tests. The results should be ".
          "coming in soon. You should probably wait for them before accepting ".
          "this diff.";
      } else if ($diff->getUnitStatus() == DifferentialUnitStatus::UNIT_SKIP) {
        $content =
          "Unit tests were skipped when this diff was created. Make sure ".
          "you are OK with that before you accept this diff.";
      } else {
        $content =
          "This diff has Unit Test Problems. Make sure you are OK with ".
          "them before you accept this diff.";
      }
      $unit_warning = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
        ->appendChild(phutil_tag('p', array(), $content))
        ->setTitle(idx($titles, $diff->getUnitStatus(), 'Warning'));
    }
    return $unit_warning;
  }

}
