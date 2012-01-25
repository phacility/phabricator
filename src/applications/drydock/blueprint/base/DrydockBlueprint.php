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

abstract class DrydockBlueprint {

  abstract public function getType();
  abstract public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type);

  public function canAllocateResources() {
    return false;
  }

  public function allocateResource() {
    throw new Exception("This blueprint can not allocate resources!");
  }

  public static function getAllBlueprints() {
    static $list = null;

    if ($list === null) {
      $blueprints = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setAncestorClass('DrydockBlueprint')
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();
      $list = ipull($blueprints, 'name', 'name');
      foreach ($list as $class_name => $ignored) {
        $list[$class_name] = newv($class_name, array());
      }
    }

    return $list;
  }

  public static function getAllBlueprintsForResource($type) {
    static $groups = null;
    if ($groups === null) {
      $groups = mgroup(self::getAllBlueprints(), 'getType');
    }
    return idx($groups, $type, array());
  }

}
