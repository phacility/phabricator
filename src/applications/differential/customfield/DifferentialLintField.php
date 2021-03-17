<?php

final class DifferentialLintField
  extends DifferentialHarbormasterField {

  public function getFieldKey() {
    return 'differential:lint';
  }

  public function getFieldName() {
    return pht('Lint');
  }

  public function getFieldDescription() {
    return pht('Shows lint results.');
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
    return 'arc:lint';
  }

  protected function getDiffPropertyKeys() {
    return array(
      'arc:lint',
    );
  }

  protected function loadHarbormasterTargetMessages(array $target_phids) {
    return id(new HarbormasterBuildLintMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls) LIMIT 25',
      $target_phids);
  }

  protected function newHarbormasterMessageView(array $messages) {
    return id(new HarbormasterLintPropertyView())
      ->setLimit(25)
      ->setLintMessages($messages);
  }

  protected function newModernMessage(array $message) {
    return HarbormasterBuildLintMessage::newFromDictionary(
      new HarbormasterBuildTarget(),
      $this->getModernLintMessageDictionary($message));
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getLintStatus();
    if ($status < DifferentialLintStatus::LINT_WARN) {
      return array();
    }
    if ($status == DifferentialLintStatus::LINT_AUTO_SKIP) {
      return array();
    }

    $warnings = array();
    if ($status == DifferentialLintStatus::LINT_SKIP) {
      $warnings[] = pht(
        'Lint was skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have lint problems.');
    }

    return $warnings;
  }

  protected function renderHarbormasterStatus(
    DifferentialDiff $diff,
    array $messages) {

    $status_value = $diff->getLintStatus();
    $status = DifferentialLintStatus::newStatusFromValue($status_value);

    $status_icon = $status->getIconIcon();
    $status_color = $status->getIconColor();
    $status_name = $status->getName();

    $status = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($status_icon, $status_color)
          ->setTarget($status_name));

    return $status;
  }

  private function getModernLintMessageDictionary(array $map) {
    // Strip out `null` values to satisfy stricter typechecks.
    foreach ($map as $key => $value) {
      if ($value === null) {
        unset($map[$key]);
      }
    }

    // TODO: We might need to remap some stuff here?
    return $map;
  }


}
