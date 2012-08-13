<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorOwnersOwner extends PhabricatorOwnersDAO {

  protected $packageID;

  // this can be a project or a user. We assume that all members of a project
  // owner also own the package; use the loadAffiliatedUserPHIDs method if
  // you want to recursively grab all user ids that own a package
  protected $userPHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
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
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      array());

    $users_in_project_phids = array();
    $project_phids = idx($all_phids, PhabricatorPHIDConstants::PHID_TYPE_PROJ);
    if ($project_phids) {
      $query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($project_phids)
        ->withEdgeTypes(array(PhabricatorEdgeConfig::TYPE_PROJ_MEMBER));
      $query->execute();
      $users_in_project_phids = $query->getDestinationPHIDs();
    }

    return array_unique(array_merge($users_in_project_phids, $user_phids));
  }
}
