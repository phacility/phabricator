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
 * @task  info  Application Information
 * @task  fact  Fact Integration
 * @task  meta  Application Management
 * @group apps
 */
abstract class PhabricatorApplication {


/* -(  Application Information  )-------------------------------------------- */


  public function getName() {
    return substr(__CLASS__, strlen('PhabricatorApplication'));
  }

  public function isEnabled() {
    return true;
  }


/* -(  Fact Integration  )--------------------------------------------------- */


  public function getFactObjectsForAnalysis() {
    return array();
  }


/* -(  Application Management  )--------------------------------------------- */


  public static function getAllInstalledApplications() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $apps = array();
    foreach ($classes as $class) {
      $app = newv($class['name'], array());
      if (!$app->isEnabled()) {
        continue;
      }
      $apps[] = $app;
    }

    return $apps;
  }


}
