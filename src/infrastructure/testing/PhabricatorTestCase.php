<?php

abstract class PhabricatorTestCase extends PhutilTestCase {

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
  private static $testsAreRunning = 0;

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

  public function willRunTestCases(array $test_cases) {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/scripts/__init_script__.php';

    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES]) {
      ++self::$storageFixtureReferences;
      if (!self::$storageFixture) {
        self::$storageFixture = $this->newStorageFixture();
      }
    }

    ++self::$testsAreRunning;
  }

  public function didRunTestCases(array $test_cases) {
    if (self::$storageFixture) {
      self::$storageFixtureReferences--;
      if (!self::$storageFixtureReferences) {
        self::$storageFixture = null;
      }
    }

    --self::$testsAreRunning;
  }

  protected function willRunTests() {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::beginIsolateAllLiskEffectsToCurrentProcess();
    }

    $this->env = PhabricatorEnv::beginScopedEnv();

    // NOTE: While running unit tests, we act as though all applications are
    // installed, regardless of the install's configuration. Tests which need
    // to uninstall applications are responsible for adjusting state themselves
    // (such tests are exceedingly rare).

    $this->env->overrideEnvConfig(
      'phabricator.uninstalled-applications',
      array());
    $this->env->overrideEnvConfig(
      'phabricator.show-prototypes',
      true);

    // Reset application settings to defaults, particularly policies.
    $this->env->overrideEnvConfig(
      'phabricator.application-settings',
      array());

    // We can't stub this service right now, and it's not generally useful
    // to publish notifications about test execution.
    $this->env->overrideEnvConfig(
      'notification.servers',
      array());

    $this->env->overrideEnvConfig(
      'phabricator.base-uri',
      'http://phabricator.example.com');

    $this->env->overrideEnvConfig(
      'auth.email-domains',
      array());

    // Tests do their own stubbing/voiding for events.
    $this->env->overrideEnvConfig('phabricator.silent', false);

    $this->env->overrideEnvConfig('cluster.read-only', false);
  }

  protected function didRunTests() {
    $config = $this->getComputedConfiguration();

    if ($config[self::PHABRICATOR_TESTCONFIG_ISOLATE_LISK]) {
      LiskDAO::endIsolateAllLiskEffectsToCurrentProcess();
    }

    try {
      if (phutil_is_hiphop_runtime()) {
        $this->env->__destruct();
      }
      unset($this->env);
    } catch (Exception $ex) {
      throw new Exception(
        pht(
          'Some test called %s, but is still holding '.
          'a reference to the scoped environment!',
          'PhabricatorEnv::beginScopedEnv()'));
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
      ->setRealName(pht('Test User %s', $seed))
      ->setUserName("test{$seed}")
      ->setIsApproved(1);

    $email = id(new PhabricatorUserEmail())
      ->setAddress("testuser{$seed}@example.com")
      ->setIsVerified(1);

    $editor = new PhabricatorUserEditor();
    $editor->setActor($user);
    $editor->createNewUser($user, $email);

    // When creating a new test user, we prefill their setting cache as empty.
    // This is a little more efficient than doing a query to load the empty
    // settings.
    $user->attachRawCacheData(
      array(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES => '[]',
      ));

    return $user;
  }


  /**
   * Throws unless tests are currently executing. This method can be used to
   * guard code which is specific to unit tests and should not normally be
   * reachable.
   *
   * If tests aren't currently being executed, throws an exception.
   */
  public static function assertExecutingUnitTests() {
    if (!self::$testsAreRunning) {
      throw new Exception(
        pht(
          'Executing test code outside of test execution! '.
          'This code path can only be run during unit tests.'));
    }
  }

  protected function requireBinaryForTest($binary) {
    if (!Filesystem::binaryExists($binary)) {
      $this->assertSkipped(
        pht(
          'No binary "%s" found on this system, skipping test.',
          $binary));
    }
  }

  protected function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorUnitTestContentSource::SOURCECONST);
  }

}
