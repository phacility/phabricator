<?php

final class PhabricatorCacheSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_PHP;
  }

  protected function executeChecks() {
    $code_cache = PhabricatorOpcodeCacheSpec::getActiveCacheSpec();
    $data_cache = PhabricatorDataCacheSpec::getActiveCacheSpec();

    $issues = $code_cache->getIssues() + $data_cache->getIssues();

    foreach ($issues as $issue) {
      $this->addIssue($issue);
    }
  }

}
