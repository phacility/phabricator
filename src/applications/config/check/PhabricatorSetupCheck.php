<?php

abstract class PhabricatorSetupCheck {

  private $issues;

  abstract protected function executeChecks();

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

  final public static function willProcessRequest() {
    $issue_count = self::getOpenSetupIssueCount();
    if ($issue_count !== null) {
      // We've already run setup checks, didn't hit any fatals, and then set
      // an issue count. This means we're good and don't need to do any extra
      // work.
      return null;
    }

    $issues = self::runAllChecks();

    self::setOpenSetupIssueCount(count($issues));

    return null;
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

    $issues = array();
    foreach ($checks as $check) {
      $check->runSetupChecks();
      foreach ($check->getIssues() as $key => $issue) {
        if (isset($issues[$key])) {
          throw new Exception(
            "Two setup checks raised an issue with key '{$key}'!");
        }
        $issues[$key] = $issue;
      }
    }

    return $issues;
  }

}
