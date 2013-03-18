<?php

final class ReleephDifferentialRevisionDetailRenderer {

  public static function generateActionLink(DifferentialRevision $revision,
                                            DifferentialDiff $diff) {

    $arc_project = $diff->loadArcanistProject(); // 93us
    if (!$arc_project) {
      return;
    }

    $releeph_projects = id(new ReleephProject())->loadAllWhere(
      'arcanistProjectID = %d AND isActive = 1',
      $arc_project->getID());

    if (!$releeph_projects) {
      return;
    }

    $releeph_branches = id(new ReleephBranch())->loadAllWhere(
      'releephProjectID IN (%Ld) AND isActive = 1',
      mpull($releeph_projects, 'getID'));

    if (!$releeph_branches) {
      return;
    }

    $uri = new PhutilURI(
      '/releeph/request/differentialcreate/D'.$revision->getID());
    return array(
      'name'  => 'Releeph Request',
      'sigil' => 'workflow',
      'href'  => $uri,
      'icon'  => 'fork',
    );
  }

}
