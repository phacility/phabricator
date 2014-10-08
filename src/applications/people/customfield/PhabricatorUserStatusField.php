<?php

final class PhabricatorUserStatusField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:status';
  }

  public function getFieldName() {
    return pht('Status');
  }

  public function getFieldDescription() {
    return pht('Shows when a user is away or busy.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function isFieldEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorCalendarApplication');
  }

  public function renderPropertyViewValue(array $handles) {
    $user = $this->getObject();
    $viewer = $this->requireViewer();

    $statuses = id(new PhabricatorCalendarEvent())
      ->loadCurrentStatuses(array($user->getPHID()));
    if (!$statuses) {
      return pht('Available');
    }

    $status = head($statuses);

    return $status->getTerseSummary($viewer);
  }

}
