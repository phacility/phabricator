<?php

abstract class ProjectConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorProjectApplication');
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

      $project_icon = PhabricatorProjectIcon::getAPIName($project->getIcon());

      $result[$project->getPHID()] = array(
        'id'               => $project->getID(),
        'phid'             => $project->getPHID(),
        'name'             => $project->getName(),
        'profileImagePHID' => $project->getProfileImagePHID(),
        'icon'             => $project_icon,
        'color'            => $project->getColor(),
        'members'          => $member_phids,
        'slugs'            => $project_slugs,
        'dateCreated'      => $project->getDateCreated(),
        'dateModified'     => $project->getDateModified(),
      );
    }

    return $result;
  }

}
