<?php

/**
 * Used by unit tests to build storage fixtures.
 */
final class PhabricatorStorageFixtureScopeGuard extends Phobject {

  private $name;

  public function __construct($name) {
    $this->name = $name;

    execx(
      'php %s upgrade --force --no-adjust --namespace %s',
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
