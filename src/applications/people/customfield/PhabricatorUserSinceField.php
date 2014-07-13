<?php

final class PhabricatorUserSinceField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:since';
  }

  public function getFieldName() {
    return pht('User Since');
  }

  public function getFieldDescription() {
    return pht('Shows user join date.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    $absolute = phabricator_datetime(
      $this->getObject()->getDateCreated(),
      $this->getViewer());

    $relative = phutil_format_relative_time_detailed(
      time() - $this->getObject()->getDateCreated(),
      $levels = 2);

    return hsprintf('%s (%s)', $absolute, $relative);
  }

}
