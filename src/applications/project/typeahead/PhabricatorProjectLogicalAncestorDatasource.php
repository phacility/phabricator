<?php

final class PhabricatorProjectLogicalAncestorDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Projects');
  }

  public function getPlaceholderText() {
    return pht('Type a project name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorProjectDatasource(),
    );
  }

  protected function didEvaluateTokens(array $results) {
    $phids = array();

    foreach ($results as $result) {
      if (!is_string($result)) {
        continue;
      }
      $phids[] = $result;
    }

    $map = array();
    $skip = array();
    if ($phids) {
      $phids = array_fuse($phids);
      $viewer = $this->getViewer();

      $all_projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withAncestorProjectPHIDs($phids)
        ->execute();

      foreach ($phids as $phid) {
        $map[$phid][] = $phid;
      }

      foreach ($all_projects as $project) {
        $project_phid = $project->getPHID();
        $map[$project_phid][] = $project_phid;
        foreach ($project->getAncestorProjects() as $ancestor) {
          $ancestor_phid = $ancestor->getPHID();

          if (isset($phids[$project_phid]) && isset($phids[$ancestor_phid])) {
            // This is a descendant of some other project in the query, so
            // we don't need to query for that project. This happens if a user
            // runs a query for both "Engineering" and "Engineering > Warp
            // Drive". We can only ever match the "Warp Drive" results, so
            // we do not need to add the weaker "Engineering" constraint.
            $skip[$ancestor_phid] = true;
          }

          $map[$ancestor_phid][] = $project_phid;
        }
      }
    }

    foreach ($results as $key => $result) {
      if (!is_string($result)) {
        continue;
      }

      if (empty($map[$result])) {
        continue;
      }

      // This constraint is implied by another, stronger constraint.
      if (isset($skip[$result])) {
        unset($results[$key]);
        continue;
      }

      // If we have duplicates, don't apply the second constraint.
      $skip[$result] = true;

      $results[$key] = new PhabricatorQueryConstraint(
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        $map[$result]);
    }

    return $results;
  }

}
