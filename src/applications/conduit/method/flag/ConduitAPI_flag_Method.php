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
abstract class ConduitAPI_flag_Method extends ConduitAPIMethod {

  protected function attachHandleToFlag($flag) {
    $flag->attachHandle(
      PhabricatorObjectHandleData::loadOneHandle($flag->getObjectPHID())
    );
  }

  protected function buildFlagInfoDictionary($flag) {
    $color = $flag->getColor();
    $uri = PhabricatorEnv::getProductionURI($flag->getHandle()->getURI());

    return array(
      'id'            => $flag->getID(),
      'ownerPHID'     => $flag->getOwnerPHID(),
      'type'          => $flag->getType(),
      'objectPHID'    => $flag->getObjectPHID(),
      'reasonPHID'    => $flag->getReasonPHID(),
      'color'         => $color,
      'colorName'     => PhabricatorFlagColor::getColorName($color),
      'note'          => $flag->getNote(),
      'handle'        => array(
        'uri'         => $uri,
        'name'        => $flag->getHandle()->getName(),
        'fullname'    => $flag->getHandle()->getFullName(),
      ),
      'dateCreated'   => $flag->getDateCreated(),
      'dateModified'  => $flag->getDateModified(),
    );
  }

}
