<?php

abstract class PhabricatorTestCase extends ArcanistPhutilTestCase {

  const NAMESPACE_PREFIX = 'phabricator_unittest_';

  /**
   * If true, put Lisk in process-isolated mode for the duration of the tests so
   * that it will establish only isolated, side-effect-free database
   * connections. Defaults to true.
   *
   * NOTE: You should disable this only in rare circumstances. Unit tests should
   * not rely on external resources like databases, and should not produce
   * side effects.
   */
  const PHABRICATOR_TESTCONFIG_ISOLATE_LISK           = 'isolate-lisk';

  /**
   * If true, build storage fixtures before running tests, and connect to them
   * during test execution. This will impose a performance penalty on test
   * execution (currently, it takes roughly one second to build the fixture)
   * but allows you to perform tests which require data to be read from storage
   * after writes. The fixture is shared across all test cases in this process.
   * Defaults to false.
   *
   * NOTE: All connections to fixture storage open transactions when established
   * and roll them back when tests complete. Each test must independently
   * write data it relies on; data will not persist across tests.
   *
   * NOTE: Enabling this implies disabling process isolation.
   */
  const PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES = 'storage-fixtures';

  private $configuration;
  private $env;

  private static $storageFixtureReferences = 0;
  private static $storageFixture;
  private static $storageFixtureObjectSeed = 0;

  protected function getPhabricatorTestCaseConfiguration() {
    return array();
  }

  private function getComputedConfiguration() {
    $config = $this->getPhabricatorTestCaseConfiguration() + array(
      self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK             => true,
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES   => false,
    );

    if ($config[self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES]) {
      // Fixtures don't make sense with process isolation.
      $config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK] = false;
    }

    return $config;
  }

  protected function willRunTests() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/scripts/__init_script__.php';

    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::beginIsolateAllLiskEffectsToCurrentProcess();
    }

    if ($config[self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES]) {
      ++self::$storageFixtureReferences;
      if (!self::$storageFixture) {
        self::$storageFixture = $this->newStorageFixture();
      }
    }

    $this->env = PhabricatorEnv::beginScopedEnv();
  }

  protected function didRunTests() {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::endIsolateAllLiskEffectsToCurrentProcess();
    }

    if (self::$storageFixture) {
      self::$storageFixtureReferences--;
      if (!self::$storageFixtureReferences) {
        self::$storageFixture = null;
      }
    }

    try {
      unset($this->env);
    } catch (Exception $ex) {
      throw new Exception(
        "Some test called PhabricatorEnv::beginScopedEnv(), but is still ".
        "holding a reference to the scoped environment!");
    }
  }

  protected function willRunOneTest($test) {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES]) {
      LiskDAO::beginIsolateAllLiskEffectsToTransactions();
    }
  }

  protected function didRunOneTest($test) {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES]) {
      LiskDAO::endIsolateAllLiskEffectsToTransactions();
    }
  }

  protected function newStorageFixture() {
    $bytes = Filesystem::readRandomCharacters(24);
    $name = self::NAMESPACE_PREFIX.$bytes;

    return new PhabricatorStorageFixtureScopeGuard($name);
  }

  protected function getLink($method) {
    $phabricator_project = 'PHID-APRJ-3f1fc779edeab89b2171';
    return
      'https://secure.phabricator.com/diffusion/symbol/'.$method.
      '/?lang=php&projects='.$phabricator_project.
      '&jump=true&context='.get_class($this);
  }

  /**
   * Returns an integer seed to use when building unique identifiers (e.g.,
   * non-colliding usernames). The seed is unstable and its value will change
   * between test runs, so your tests must not rely on it.
   *
   * @return int A unique integer.
   */
  protected function getNextObjectSeed() {
    self::$storageFixtureObjectSeed += mt_rand(1, 100);
    return self::$storageFixtureObjectSeed;
  }

  protected function generateNewTestUser() {
    $seed = $this->getNextObjectSeed();

    $user = id(new PhabricatorUser())
      ->setRealName("Test User {$seed}}")
      ->setUserName("test{$seed}");

    $email = id(new PhabricatorUserEmail())
      ->setAddress("testuser{$seed}@example.com")
      ->setIsVerified(1);

    $editor = new PhabricatorUserEditor();
    $editor->setActor($user);
    $editor->createNewUser($user, $email);

    return $user;
  }

}
