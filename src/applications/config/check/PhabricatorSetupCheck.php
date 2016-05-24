<?php

abstract class PhabricatorSetupCheck extends Phobject {

  private $issues;

  abstract protected function executeChecks();

  const GROUP_OTHER       = 'other';
  const GROUP_MYSQL       = 'mysql';
  const GROUP_PHP         = 'php';
  const GROUP_IMPORTANT   = 'important';

  public function getExecutionOrder() {
    return 1;
  }

  final protected function newIssue($key) {
    $issue = id(new PhabricatorSetupIssue())
      ->setIssueKey($key);
    $this->issues[$key] = $issue;

    if ($this->getDefaultGroup()) {
      $issue->setGroup($this->getDefaultGroup());
    }

    return $issue;
  }

  final public function getIssues() {
    return $this->issues;
  }

  protected function addIssue(PhabricatorSetupIssue $issue) {
    $this->issues[$issue->getIssueKey()] = $issue;
    return $this;
  }

  public function getDefaultGroup() {
    return null;
  }

  final public function runSetupChecks() {
    $this->issues = array();
    $this->executeChecks();
  }

  final public static function getOpenSetupIssueKeys() {
    $cache = PhabricatorCaches::getSetupCache();
    return $cache->getKey('phabricator.setup.issue-keys');
  }

  final public static function setOpenSetupIssueKeys(
    array $keys,
    $update_database) {
    $cache = PhabricatorCaches::getSetupCache();
    $cache->setKey('phabricator.setup.issue-keys', $keys);

    if ($update_database) {
      $db_cache = new PhabricatorKeyValueDatabaseCache();
      try {
        $json = phutil_json_encode($keys);
        $db_cache->setKey('phabricator.setup.issue-keys', $json);
      } catch (Exception $ex) {
        // Ignore any write failures, since they likely just indicate that we
        // have a database-related setup issue that needs to be resolved.
      }
    }
  }

  final public static function getOpenSetupIssueKeysFromDatabase() {
    $db_cache = new PhabricatorKeyValueDatabaseCache();
    try {
      $value = $db_cache->getKey('phabricator.setup.issue-keys');
      if (!strlen($value)) {
        return null;
      }
      return phutil_json_decode($value);
    } catch (Exception $ex) {
      return null;
    }
  }

  final public static function getUnignoredIssueKeys(array $all_issues) {
    assert_instances_of($all_issues, 'PhabricatorSetupIssue');
    $keys = array();
    foreach ($all_issues as $issue) {
      if (!$issue->getIsIgnored()) {
        $keys[] = $issue->getIssueKey();
      }
    }
    return $keys;
  }

  final public static function getConfigNeedsRepair() {
    $cache = PhabricatorCaches::getSetupCache();
    return $cache->getKey('phabricator.setup.needs-repair');
  }

  final public static function setConfigNeedsRepair($needs_repair) {
    $cache = PhabricatorCaches::getSetupCache();
    $cache->setKey('phabricator.setup.needs-repair', $needs_repair);
  }

  final public static function deleteSetupCheckCache() {
    $cache = PhabricatorCaches::getSetupCache();
    $cache->deleteKeys(
      array(
        'phabricator.setup.needs-repair',
        'phabricator.setup.issue-keys',
      ));
  }

  final public static function willProcessRequest() {
    $issue_keys = self::getOpenSetupIssueKeys();
    if ($issue_keys === null) {
      $issues = self::runAllChecks();
      foreach ($issues as $issue) {
        if ($issue->getIsFatal()) {
          $view = id(new PhabricatorSetupIssueView())
            ->setIssue($issue);
          return id(new PhabricatorConfigResponse())
            ->setView($view);
        }
      }
      $issue_keys = self::getUnignoredIssueKeys($issues);
      self::setOpenSetupIssueKeys($issue_keys, $update_database = true);
    } else if ($issue_keys) {
      // If Phabricator is configured in a cluster with multiple web devices,
      // we can end up with setup issues cached on every device. This can cause
      // a warning banner to show on every device so that each one needs to
      // be dismissed individually, which is pretty annoying. See T10876.

      // To avoid this, check if the issues we found have already been cleared
      // in the database. If they have, we'll just wipe out our own cache and
      // move on.
      $issue_keys = self::getOpenSetupIssueKeysFromDatabase();
      if ($issue_keys !== null) {
        self::setOpenSetupIssueKeys($issue_keys, $update_database = false);
      }
    }

    // Try to repair configuration unless we have a clean bill of health on it.
    // We need to keep doing this on every page load until all the problems
    // are fixed, which is why it's separate from setup checks (which run
    // once per restart).
    $needs_repair = self::getConfigNeedsRepair();
    if ($needs_repair !== false) {
      $needs_repair = self::repairConfig();
      self::setConfigNeedsRepair($needs_repair);
    }
  }

  final public static function loadAllChecks() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getExecutionOrder')
      ->execute();
  }

  final public static function runAllChecks() {
    $checks = self::loadAllChecks();

    $issues = array();
    foreach ($checks as $check) {
      $check->runSetupChecks();
      foreach ($check->getIssues() as $key => $issue) {
        if (isset($issues[$key])) {
          throw new Exception(
            pht(
              "Two setup checks raised an issue with key '%s'!",
              $key));
        }
        $issues[$key] = $issue;
        if ($issue->getIsFatal()) {
          break 2;
        }
      }
    }

    $ignore_issues = PhabricatorEnv::getEnvConfig('config.ignore-issues');
    foreach ($ignore_issues as $ignorable => $derp) {
      if (isset($issues[$ignorable])) {
        $issues[$ignorable]->setIsIgnored(true);
      }
    }

    return $issues;
  }

  final public static function repairConfig() {
    $needs_repair = false;

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    foreach ($options as $option) {
      try {
        $option->getGroup()->validateOption(
          $option,
          PhabricatorEnv::getEnvConfig($option->getKey()));
      } catch (PhabricatorConfigValidationException $ex) {
        PhabricatorEnv::repairConfig($option->getKey(), $option->getDefault());
        $needs_repair = true;
      }
    }

    return $needs_repair;
  }

}
