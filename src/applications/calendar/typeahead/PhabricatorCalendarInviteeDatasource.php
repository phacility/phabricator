<?php

final class PhabricatorCalendarInviteeDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Invitees');
  }

  public function getPlaceholderText() {
    return pht('Type a user or project name, or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorCalendarInviteeUserDatasource(),
      new PhabricatorCalendarInviteeViewerFunctionDatasource(),
      new DifferentialExactUserFunctionDatasource(),
      new PhabricatorProjectDatasource(),
    );
  }

  public static function expandInvitees(
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
      $values[] = $project->getPHID();
    }

    return $values;
  }

}
