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
final class ConduitAPI_flag_query_Method extends ConduitAPI_flag_Method {

  public function getMethodDescription() {
    return "Query flag markers.";
  }

  public function defineParamTypes() {
    return array(
      'ownerPHIDs'    => 'optional list<phid>',
      'types'         => 'optional list<type>',
      'objectPHIDs'   => 'optional list<phid>',

      'offset'        => 'optional int',
      'limit'         => 'optional int (default = 100)',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $query = new PhabricatorFlagQuery();

    $owner_phids = $request->getValue('ownerPHIDs', array());
    if ($owner_phids) {
      $query->withOwnerPHIDs($owner_phids);
    }

    $object_phids = $request->getValue('objectPHIDs', array());
    if ($object_phids) {
      $query->withObjectPHIDs($object_phids);
    }

    $types = $request->getValue('types', array());
    if ($types) {
      $query->withTypes($types);
    }

    $query->needHandles(true);

    $query->setOffset($request->getValue('offset', 0));
    $query->setLimit($request->getValue('limit', 100));

    $flags = $query->execute();

    $results = array();
    foreach ($flags as $flag) {
      $results[] = $this->buildFlagInfoDictionary($flag);
    }

    return $results;
  }

}
