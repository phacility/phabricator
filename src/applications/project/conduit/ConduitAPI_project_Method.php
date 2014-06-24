<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_project_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationProject');
  }

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

      $project_slugs = $project->getSlugs();
      $project_slugs = array_values(mpull($project_slugs, 'getSlug'));

      $result[$project->getPHID()] = array(
        'id'            => $project->getID(),
        'phid'          => $project->getPHID(),
        'name'          => $project->getName(),
        'members'       => $member_phids,
        'slugs'         => $project_slugs,
        'dateCreated'   => $project->getDateCreated(),
        'dateModified'  => $project->getDateModified(),
      );
    }

    return $result;
  }

}
