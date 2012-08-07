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

final class PhabricatorPHID {

  protected $phid;
  protected $phidType;
  protected $ownerPHID;
  protected $parentPHID;

  public static function generateNewPHID($type) {
    if (!$type) {
      throw new Exception("Can not generate PHID with no type.");
    }

    $uniq = Filesystem::readRandomCharacters(20);
    return 'PHID-'.$type.'-'.$uniq;
  }

  public static function fromObjectName($name) {
    $object = null;
    $match = null;
    if (preg_match('/^PHID-[A-Z]+-.{20}$/', $name)) {
      // It's already a PHID! Yay.
      return $name;
    }
    if (preg_match('/^r([A-Z]+)(\S*)$/', $name, $match)) {
      $repository = id(new PhabricatorRepository())
        ->loadOneWhere('callsign = %s', $match[1]);
      if ($match[2] == '') {
        $object = $repository;
      } else if ($repository) {
        $object = id(new PhabricatorRepositoryCommit())->loadOneWhere(
          'repositoryID = %d AND commitIdentifier = %s',
          $repository->getID(),
          $match[2]);
        if (!$object) {
          try {
            $object = id(new PhabricatorRepositoryCommit())->loadOneWhere(
              'repositoryID = %d AND commitIdentifier LIKE %>',
              $repository->getID(),
              $match[2]);
          } catch (AphrontQueryCountException $ex) {
            // Ambiguous; return nothing.
          }
        }
      }
    } else if (preg_match('/^d(\d+)$/i', $name, $match)) {
      $object = id(new DifferentialRevision())->load($match[1]);
    } else if (preg_match('/^t(\d+)$/i', $name, $match)) {
      $object = id(new ManiphestTask())->load($match[1]);
    }
    if ($object) {
      return $object->getPHID();
    }
    return null;
  }
}
