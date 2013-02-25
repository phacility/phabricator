<?php

abstract class PhabricatorSetupCheck {

  private $issues;

  abstract protected function executeChecks();

  public function getExecutionOrder() {
    return 1;
  }

  final protected function newIssue($key) {
    $issue = id(new PhabricatorSetupIssue())
      ->setIssueKey($key);
    $this->issues[$key] = $issue;

    return $issue;
  }

  final public function getIssues() {
    return $this->issues;
  }

  final public function runSetupChecks() {
    $this->issues = array();
    $this->executeChecks();
  }

  final public static function getOpenSetupIssueCount() {
    $cache = PhabricatorCaches::getSetupCache();
    return $cache->getKey('phabricator.setup.issues');
  }

  final public static function setOpenSetupIssueCount($count) {
    $cache = PhabricatorCaches::getSetupCache();
    $cache->setKey('phabricator.setup.issues', $count);
  }

  final public static function countUnignoredIssues(array $all_issues) {
    assert_instances_of($all_issues, 'PhabricatorSetupIssue');
    $count = 0;
    foreach ($all_issues as $issue) {
      if (!$issue->getIsIgnored()) {
        $count++;
      }
    }
    return $count;
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
        'phabricator.setup.issues',
      ));
  }

  final public static function willProcessRequest() {
    $issue_count = self::getOpenSetupIssueCount();
    if ($issue_count === null) {
      $issues = self::runAllChecks();
      foreach ($issues as $issue) {
        if ($issue->getIsFatal()) {
          $view = id(new PhabricatorSetupIssueView())
            ->setIssue($issue);
          return id(new PhabricatorConfigResponse())
            ->setView($view);
        }
      }
      self::setOpenSetupIssueCount(self::countUnignoredIssues($issues));
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
      ->setAncestorClass('PhabricatorSetupCheck')
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
            "Two setup checks raised an issue with key '{$key}'!");
        }
        $issues[$key] = $issue;
        if ($issue->getIsFatal()) {
          break 2;
        }
      }
    }

    foreach (PhabricatorEnv::getEnvConfig('config.ignore-issues')
              as $ignorable => $derp) {
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
