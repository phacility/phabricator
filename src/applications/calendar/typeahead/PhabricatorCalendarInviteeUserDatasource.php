<?php

final class PhabricatorCalendarInviteeUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users');
  }

  public function getPlaceholderText() {
    return pht('Type a user name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  protected function evaluateValues(array $values) {
    return PhabricatorCalendarInviteeDatasource::expandInvitees(
      $this->getViewer(),
      $values);
  }

}
