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

class PhabricatorPHID extends PhabricatorPHIDDAO {

  protected $phid;
  protected $phidType;
  protected $ownerPHID;
  protected $parentPHID;

  public static function generateNewPHID($type, array $config = array()) {
    $owner  = idx($config, 'owner');
    $parent = idx($config, 'parent');

    if (!$type) {
      throw new Exception("Can not generate PHID with no type.");
    }

    $uniq = Filesystem::readRandomCharacters(20);
    $phid = 'PHID-'.$type.'-'.$uniq;

    $phid_rec = new PhabricatorPHID();
    $phid_rec->setPHIDType($type);
    $phid_rec->setOwnerPHID($owner);
    $phid_rec->setParentPHID($parent);
    $phid_rec->setPHID($phid);
    $phid_rec->save();

    return $phid;
  }

}
