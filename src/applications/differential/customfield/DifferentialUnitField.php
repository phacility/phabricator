<?php

final class DifferentialUnitField
  extends DifferentialHarbormasterField {

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

  protected function getLegacyProperty() {
    return 'arc:unit';
  }

  protected function getDiffPropertyKeys() {
    return array(
      'arc:unit',
      'arc:unit-excuse',
    );
  }

  protected function loadHarbormasterTargetMessages(array $target_phids) {
    return id(new HarbormasterBuildUnitMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls) LIMIT 25',
      $target_phids);
  }

  protected function newModernMessage(array $message) {
    return HarbormasterBuildUnitMessage::newFromDictionary(
      new HarbormasterBuildTarget(),
      $this->getModernUnitMessageDictionary($message));
  }

  protected function newHarbormasterMessageView(array $messages) {
    return id(new HarbormasterUnitPropertyView())
      ->setUnitMessages($messages);
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getUnitStatus();

    $warnings = array();
    if ($status < DifferentialUnitStatus::UNIT_WARN) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_AUTO_SKIP) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_POSTPONED) {
      $warnings[] = pht(
        'Background tests have not finished executing on these changes.');
    } else if ($status == DifferentialUnitStatus::UNIT_SKIP) {
      $warnings[] = pht(
        'Unit tests were skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have unit test problems.');
    }

    return $warnings;
  }

  protected function renderHarbormasterStatus(
    DifferentialDiff $diff,
    array $messages) {

    $colors = array(
      DifferentialUnitStatus::UNIT_NONE => 'grey',
      DifferentialUnitStatus::UNIT_OKAY => 'green',
      DifferentialUnitStatus::UNIT_WARN => 'yellow',
      DifferentialUnitStatus::UNIT_FAIL => 'red',
      DifferentialUnitStatus::UNIT_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_AUTO_SKIP => 'blue',
      DifferentialUnitStatus::UNIT_POSTPONED => 'blue',
    );
    $icon_color = idx($colors, $diff->getUnitStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $excuse = $diff->getProperty('arc:unit-excuse');
    if (strlen($excuse)) {
      $excuse = array(
        phutil_tag('strong', array(), pht('Excuse:')),
        ' ',
        phutil_escape_html_newlines($excuse),
      );
    }

    $status = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_STAR, $icon_color)
          ->setTarget($message)
          ->setNote($excuse));

    return $status;
  }

  private function getModernUnitMessageDictionary(array $map) {
    // Strip out `null` values to satisfy stricter typechecks.
    foreach ($map as $key => $value) {
      if ($value === null) {
        unset($map[$key]);
      }
    }

    // TODO: Remap more stuff here?

    return $map;
  }


}
