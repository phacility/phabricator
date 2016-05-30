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

    $type_user = PhabricatorPeopleUserPHIDType::TYPECONST;
    $type_project = PhabricatorProjectProjectPHIDType::TYPECONST;

    $user_phids = array();
    $project_phids = array();
    foreach ($owners as $owner) {
      $owner_phid = $owner->getUserPHID();
      switch (phid_get_type($owner_phid)) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
          $user_phids[] = $owner_phid;
          break;
        case PhabricatorProjectProjectPHIDType::TYPECONST:
          $project_phids[] = $owner_phid;
          break;
      }
    }

    if ($project_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($project_phids)
        ->needMembers(true)
        ->execute();
      foreach ($projects as $project) {
        foreach ($project->getMemberPHIDs() as $member_phid) {
          $user_phids[] = $member_phid;
        }
      }
    }

    $user_phids = array_fuse($user_phids);
    return array_values($user_phids);
  }
}
