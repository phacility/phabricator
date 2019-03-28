<?php

final class PhabricatorUserStatusField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:status';
  }

  public function getFieldName() {
    return pht('Availability');
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

    // Don't show availability for disabled users, since this is vaguely
    // misleading to say "Availability: Available" and probably not useful.
    if ($user->getIsDisabled()) {
      return null;
    }

    return id(new PHUIUserAvailabilityView())
      ->setViewer($viewer)
      ->setAvailableUser($user);
  }

}
