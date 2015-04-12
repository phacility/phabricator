<?php

final class ReleephProjectInfoConduitAPIMethod extends ReleephConduitAPIMethod {

  public function getAPIMethodName() {
    return 'releeph.projectinfo';
  }

  public function getMethodDescription() {
    return
      'Fetch information about all Releeph projects '.
      'for a given Arcanist project.';
  }

  protected function defineParamTypes() {
    return array(
      'arcProjectName' => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'dict<string, wild>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_UNKNOWN_ARC' =>
        "The given Arcanist project name doesn't exist in the ".
        "installation of Phabricator you are accessing.",
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $arc_project_name = $request->getValue('arcProjectName');
    if ($arc_project_name) {
      $arc_project = id(new PhabricatorRepositoryArcanistProject())
        ->loadOneWhere('name = %s', $arc_project_name);
      if (!$arc_project) {
        throw id(new ConduitException('ERR_UNKNOWN_ARC'))
          ->setErrorDescription(
            "Unknown Arcanist project '{$arc_project_name}': ".
            "are you using the correct Conduit URI?");
      }

      $releeph_projects = id(new ReleephProject())
        ->loadAllWhere('arcanistProjectID = %d', $arc_project->getID());
    } else {
      $releeph_projects = id(new ReleephProject())->loadAll();
    }

    $releeph_projects = mfilter($releeph_projects, 'getIsActive');

    $result = array();
    foreach ($releeph_projects as $releeph_project) {
      $selector = $releeph_project->getReleephFieldSelector();
      $fields = $selector->getFieldSpecifications();

      $fields_info = array();
      foreach ($fields as $field) {
        $field->setReleephProject($releeph_project);
        if ($field->isEditable()) {
          $key = $field->getKeyForConduit();
          $fields_info[$key] = array(
            'class'   => get_class($field),
            'name'    => $field->getName(),
            'key'     => $key,
            'arcHelp' => $field->renderHelpForArcanist(),
          );
        }
      }

      $releeph_branches = mfilter(
        id(new ReleephBranch())
          ->loadAllWhere('releephProjectID = %d', $releeph_project->getID()),
        'getIsActive');

      $releeph_branches_struct = array();
      foreach ($releeph_branches as $branch) {
        $releeph_branches_struct[] = array(
          'branchName'  => $branch->getName(),
          'projectName' => $releeph_project->getName(),
          'projectPHID' => $releeph_project->getPHID(),
          'branchPHID'  => $branch->getPHID(),
        );
      }

      $result[] = array(
        'projectName' => $releeph_project->getName(),
        'projectPHID' => $releeph_project->getPHID(),
        'branches'    => $releeph_branches_struct,
        'fields'      => $fields_info,
      );
    }

    return $result;
  }

}
