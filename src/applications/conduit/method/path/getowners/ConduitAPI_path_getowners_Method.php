<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ConduitAPI_path_getowners_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Find owners package given its name";
  }

  public function defineParamTypes() {
    return array(
      'repositoryCallsign' => 'required nonempty string',
      'path' => 'required nonempty string'
    );
  }

  public function defineReturnType() {
    return 'array of packages containing phid, primary_owner (phid=>username),'.
      'owners(array of phid=>username)';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_REP_NOT_FOUND'  => 'The repository callsign is not recognized',
      'ERR_PATH_NOT_FOUND' => 'The specified path is not known to any package',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $repository = id(new PhabricatorRepository())->loadOneWhere('callsign = %s',
      $request->getValue('repositoryCallsign'));

    if (empty($repository)) {
      throw new ConduitException('ERR_REP_NOT_FOUND');
    }

    $packages = PhabricatorOwnersPackage::loadOwningPackages(
      $repository, $request->getValue('path'));
    if (empty($packages)) {
      throw new ConduitException('ERR_PATH_NOT_FOUND');
    }

    $owner = new PhabricatorOwnersOwner();
    $user = new PhabricatorUser();
    $result = array();
    foreach ($packages as $package) {
      $p_result = array();
      $p_result['phid'] = $package->getID();
      $primary_owner_phid = $package->getPrimaryOwnerPHID();
      if (!empty($primary_owner_phid)) {
        $p_user =  $user->loadOneWhere('phid = %s',
          $primary_owner_phid);
        $p_result['primaryOwner'] = $p_user->getPhid();
      }

      $p_owners = $owner->loadAllForPackages(array($package));
      $p_users = $user->loadAllWhere('phid IN (%Ls)',
        mpull($p_owners, 'getUserPHID'));

      $p_result['owners'] = array_values(mpull($p_users, 'getPhid'));

      $result[] = $p_result;
    }

    return $result;
  }

}
