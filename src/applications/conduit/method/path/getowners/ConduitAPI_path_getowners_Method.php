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

/**
 * @group conduit
 */
final class ConduitAPI_path_getowners_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Find the Owners package that contains a given path.";
  }

  public function defineParamTypes() {
    return array(
      'repositoryCallsign' => 'required nonempty string',
      'path' => 'required nonempty string'
    );
  }

  public function defineReturnType() {
    return
      "array(".
        "array(".
          "'phid' => phid, ".
          "'name' => string, ".
          "'primaryOwner' => phid, ".
          "'owners' => array(phid)))";
  }

  public function defineErrorTypes() {
    return array(
      'ERR_REP_NOT_FOUND'  => 'The repository callsign is not recognized',
      'ERR_PATH_NOT_FOUND' => 'The specified path is not in any package',
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

    $result = array();
    foreach ($packages as $package) {
      $p_owners =
        id(new PhabricatorOwnersOwner())->loadAllForPackages(array($package));

      $result[] = array(
        'phid' => $package->getPHID(),
        'name' => $package->getName(),
        'primaryOwner' => $package->getPrimaryOwnerPHID(),
        'owners' => array_values(mpull($p_owners, 'getUserPHID')),
      );
    }

    return $result;
  }

}
