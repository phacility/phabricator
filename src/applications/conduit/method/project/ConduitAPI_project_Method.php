<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_project_Method extends ConduitAPIMethod {

  protected function buildProjectInfoDictionary(PhabricatorProject $project) {
    $results = $this->buildProjectInfoDictionaries(array($project));
    return idx($results, $project->getPHID());
  }

  protected function buildProjectInfoDictionaries(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');
    if (!$projects) {
      return array();
    }

    $result = array();
    foreach ($projects as $project) {

      $member_phids = $project->getMemberPHIDs();
      $member_phids = array_values($member_phids);

      $result[$project->getPHID()] = array(
        'id'            => $project->getID(),
        'phid'          => $project->getPHID(),
        'name'          => $project->getName(),
        'members'       => $member_phids,
        'dateCreated'   => $project->getDateCreated(),
        'dateModified'  => $project->getDateModified(),
      );
    }

    return $result;
  }

}
