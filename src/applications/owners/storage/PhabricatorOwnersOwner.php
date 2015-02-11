<?php

final class PhabricatorOwnersOwner extends PhabricatorOwnersDAO {

  protected $packageID;

  // this can be a project or a user. We assume that all members of a project
  // owner also own the package; use the loadAffiliatedUserPHIDs method if
  // you want to recursively grab all user ids that own a package
  protected $userPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_KEY_SCHEMA => array(
        'packageID' => array(
          'columns' => array('packageID', 'userPHID'),
          'unique' => true,
        ),
        'userPHID' => array(
          'columns' => array('userPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function loadAllForPackages(array $packages) {
    assert_instances_of($packages, 'PhabricatorOwnersPackage');
    if (!$packages) {
      return array();
    }
    return id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID IN (%Ls)',
      mpull($packages, 'getID'));
  }

  // Loads all user phids affiliated with a set of packages. This includes both
  // user owners and all members of any project owners
  public static function loadAffiliatedUserPHIDs(array $package_ids) {
    if (!$package_ids) {
      return array();
    }
    $owners = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID IN (%Ls)',
      $package_ids);

    $all_phids = phid_group_by_type(mpull($owners, 'getUserPHID'));

    $user_phids = idx($all_phids,
      PhabricatorPeopleUserPHIDType::TYPECONST,
      array());

    $users_in_project_phids = array();
    $project_phids = idx(
      $all_phids,
      PhabricatorProjectProjectPHIDType::TYPECONST);
    if ($project_phids) {
      $query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($project_phids)
        ->withEdgeTypes(array(
          PhabricatorProjectProjectHasMemberEdgeType::EDGECONST,
        ));
      $query->execute();
      $users_in_project_phids = $query->getDestinationPHIDs();
    }

    return array_unique(array_merge($users_in_project_phids, $user_phids));
  }
}
