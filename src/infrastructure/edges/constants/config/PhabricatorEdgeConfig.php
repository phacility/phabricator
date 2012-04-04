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

final class PhabricatorEdgeConfig extends PhabricatorEdgeConstants {

  const TABLE_NAME_EDGE       = 'edge';
  const TABLE_NAME_EDGEDATA   = 'edgedata';

  const TYPE_TASK_HAS_COMMIT  = 1;
  const TYPE_COMMIT_HAS_TASK  = 2;

  public static function getInverse($edge_type) {
    static $map = array(
      self::TYPE_TASK_HAS_COMMIT => self::TYPE_COMMIT_HAS_TASK,
      self::TYPE_COMMIT_HAS_TASK => self::TYPE_TASK_HAS_COMMIT,
    );

    return idx($map, $edge_type);
  }

  public static function establishConnection($phid_type, $conn_type) {
    static $class_map = array(
      PhabricatorPHIDConstants::PHID_TYPE_TASK  => 'ManiphestTask',
      PhabricatorPHIDConstants::PHID_TYPE_CMIT  => 'PhabricatorRepository',
      PhabricatorPHIDConstants::PHID_TYPE_DREV  => 'DifferentialRevision',
      PhabricatorPHIDConstants::PHID_TYPE_FILE  => 'PhabricatorFile',
      PhabricatorPHIDConstants::PHID_TYPE_USER  => 'PhabricatorUser',
      PhabricatorPHIDConstants::PHID_TYPE_PROJ  => 'PhabricatorProject',
      PhabricatorPHIDConstants::PHID_TYPE_MLST  =>
        'PhabricatorMetaMTAMailingList',
    );

    $class = idx($class_map, $phid_type);

    if (!$class) {
      throw new Exception(
        "Edges are not available for objects of type '{$phid_type}'!");
    }

    return newv($class, array())->establishConnection($conn_type);
  }


}
