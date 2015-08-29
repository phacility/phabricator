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
      'arc:lint-excuse',
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

    $colors = array(
      DifferentialLintStatus::LINT_NONE => 'grey',
      DifferentialLintStatus::LINT_OKAY => 'green',
      DifferentialLintStatus::LINT_WARN => 'yellow',
      DifferentialLintStatus::LINT_FAIL => 'red',
      DifferentialLintStatus::LINT_SKIP => 'blue',
      DifferentialLintStatus::LINT_AUTO_SKIP => 'blue',
    );
    $icon_color = idx($colors, $diff->getLintStatus(), 'grey');

    $message = DifferentialRevisionUpdateHistoryView::getDiffLintMessage($diff);

    $excuse = $diff->getProperty('arc:lint-excuse');
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
