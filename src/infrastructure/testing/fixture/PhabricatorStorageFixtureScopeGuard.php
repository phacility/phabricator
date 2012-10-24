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
 * Used by unit tests to build storage fixtures.
 */
final class PhabricatorStorageFixtureScopeGuard {

  private $name;

  public function __construct($name) {
    $this->name = $name;

    execx(
      'php %s upgrade --force --namespace %s',
      $this->getStorageBinPath(),
      $this->name);

    PhabricatorLiskDAO::pushStorageNamespace($name);

    // Destructor is not called with fatal error.
    register_shutdown_function(array($this, 'destroy'));
  }

  public function destroy() {
    PhabricatorLiskDAO::popStorageNamespace();

    execx(
      'php %s destroy --force --namespace %s',
      $this->getStorageBinPath(),
      $this->name);
  }

  private function getStorageBinPath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/scripts/sql/manage_storage.php';
  }

}
