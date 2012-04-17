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

abstract class PhabricatorTestCase extends ArcanistPhutilTestCase {


  /**
   * If true, put Lisk in process-isolated mode for the duration of the tests so
   * that it will establish only isolated, side-effect-free database
   * connections. Defaults to true.
   *
   * NOTE: You should disable this only in rare circumstances. Unit tests should
   * not rely on external resources like databases, and should not produce
   * side effects.
   */
  const PHABRICATOR_TESTCONFIG_ISOLATE_LISK     = 'isolate-lisk';

  private $configuration;
  private $env;

  protected function getPhabricatorTestCaseConfiguration() {
    return array();
  }

  private function getComputedConfiguration() {
    return $this->getPhabricatorTestCaseConfiguration() + array(
      self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK     => true,
    );
  }

  protected function willRunTests() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/scripts/__init_script__.php';

    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::beginIsolateAllLiskEffectsToCurrentProcess();
    }

    $this->env = PhabricatorEnv::beginScopedEnv();
  }

  protected function didRunTests() {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::endIsolateAllLiskEffectsToCurrentProcess();
    }

    try {
      unset($this->env);
    } catch (Exception $ex) {
      throw new Exception(
        "Some test called PhabricatorEnv::beginScopedEnv(), but is still ".
        "holding a reference to the scoped environment!");
    }
  }

}
