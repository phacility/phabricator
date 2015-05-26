<?php

abstract class PhabricatorSetupCheck {

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

  final public static function setOpenSetupIssueKeys(array $keys) {
    $cache = PhabricatorCaches::getSetupCache();
    $cache->setKey('phabricator.setup.issue-keys', $keys);
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
      self::setOpenSetupIssueKeys(self::getUnignoredIssueKeys($issues));
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

  final public static function runAllChecks() {
    $symbols = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $checks = array();
    foreach ($symbols as $symbol) {
      $checks[] = newv($symbol['name'], array());
    }

    $checks = msort($checks, 'getExecutionOrder');

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
