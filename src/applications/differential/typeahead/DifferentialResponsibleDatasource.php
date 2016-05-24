<?php

final class DifferentialResponsibleDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Responsible Users');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, or package name, or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new DifferentialResponsibleUserDatasource(),
      new DifferentialResponsibleViewerFunctionDatasource(),
      new DifferentialExactUserFunctionDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
    );
  }

  public static function expandResponsibleUsers(
    PhabricatorUser $viewer,
    array $values) {

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
