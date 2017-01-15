<?php

final class DifferentialUnitField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:unit';
  }

  public function getFieldName() {
    return pht('Unit');
  }

  public function getFieldDescription() {
    return pht('Shows unit test results.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function shouldAppearInDiffPropertyView() {
    return true;
  }

  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    return $this->getFieldName();
  }

  public function getWarningsForDetailView() {
    $warnings = array();

    $viewer = $this->getViewer();
    $diff = $this->getObject()->getActiveDiff();

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($diff->getPHID()))
      ->withManualBuildables(false)
      ->executeOne();
    if ($buildable) {
      switch ($buildable->getBuildableStatus()) {
        case HarbormasterBuildable::STATUS_BUILDING:
          $warnings[] = pht(
            'These changes have not finished building yet and may have build '.
            'failures.');
          break;
        case HarbormasterBuildable::STATUS_FAILED:
          $warnings[] = pht(
            'These changes have failed to build.');
          break;
      }
    }

    $status = $this->getObject()->getActiveDiff()->getUnitStatus();
    if ($status < DifferentialUnitStatus::UNIT_WARN) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_AUTO_SKIP) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_SKIP) {
      $warnings[] = pht(
        'Unit tests were skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have unit test problems.');
    }

    return $warnings;
  }

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {

    $colors = array(
      DifferentialUnitStatus::UNIT_NONE => 'grey',
      DifferentialUnitStatus::UNIT_OKAY => 'green',
      DifferentialUnitStatus::UNIT_WARN => 'yellow',
      DifferentialUnitStatus::UNIT_FAIL => 'red',
      DifferentialUnitStatus::UNIT_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_AUTO_SKIP => 'blue',
    );
    $icon_color = idx($colors, $diff->getUnitStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage(
      $diff->getUnitStatus());

    $status = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_STAR, $icon_color)
          ->setTarget($message));

    return $status;
  }



}
