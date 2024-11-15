<?php

final class PhabricatorRoleLogicalAncestorDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Roles');
  }

  public function getPlaceholderText() {
    return pht('Type a role name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorRoleDatasource(),
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

      $all_roles = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withAncestorRolePHIDs($phids)
        ->execute();

      foreach ($phids as $phid) {
        $map[$phid][] = $phid;
      }

      foreach ($all_roles as $role) {
        $role_phid = $role->getPHID();
        $map[$role_phid][] = $role_phid;
        foreach ($role->getAncestorRoles() as $ancestor) {
          $ancestor_phid = $ancestor->getPHID();

          if (isset($phids[$role_phid]) && isset($phids[$ancestor_phid])) {
            // This is a descendant of some other role in the query, so
            // we don't need to query for that role. This happens if a user
            // runs a query for both "Engineering" and "Engineering > Warp
            // Drive". We can only ever match the "Warp Drive" results, so
            // we do not need to add the weaker "Engineering" constraint.
            $skip[$ancestor_phid] = true;
          }

          $map[$ancestor_phid][] = $role_phid;
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
