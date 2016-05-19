<?php

final class DifferentialResponsibleUserDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Users');
  }

  public function getPlaceholderText() {
    return pht('Type a user name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
    );
  }

  protected function evaluateValues(array $values) {
    return DifferentialResponsibleDatasource::expandResponsibleUsers(
      $this->getViewer(),
      $values);
  }

}
