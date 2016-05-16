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
    $viewer = $this->getViewer();

    $phids = array();
    foreach ($values as $value) {
      if (phid_get_type($value) == PhabricatorPeopleUserPHIDType::TYPECONST) {
        $phids[] = $value;
      }
    }

    if (!$phids) {
      return $values;
    }

    $projects = id(new PhabricatorProjectQuery())
       ->setViewer($viewer)
       ->withMemberPHIDs($phids)
       ->execute();
    foreach ($projects as $project) {
      $phids[] = $project->getPHID();
      $values[] = $project->getPHID();
    }

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withOwnerPHIDs($phids)
      ->execute();
    foreach ($packages as $package) {
      $values[] = $package->getPHID();
    }

    return $values;
  }

}
