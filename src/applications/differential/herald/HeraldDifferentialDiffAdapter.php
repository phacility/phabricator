<?php

final class HeraldDifferentialDiffAdapter extends HeraldDifferentialAdapter {

  public function getAdapterApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function initializeNewAdapter() {
    $this->setDiff(new DifferentialDiff());
  }

  public function isSingleEventAdapter() {
    return true;
  }

  protected function loadChangesets() {
    return $this->loadChangesetsWithHunks();
  }

  protected function loadChangesetsWithHunks() {
    return $this->getDiff()->getChangesets();
  }

  public function getObject() {
    return $this->getDiff();
  }

  public function getAdapterContentType() {
    return 'differential.diff';
  }

  public function getAdapterContentName() {
    return pht('Differential Diffs');
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to new diffs being uploaded, before writes occur.\n".
      "These rules can reject diffs before they are written to permanent ".
      "storage, to prevent users from accidentally uploading private keys or ".
      "other sensitive information.");
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      default:
        return false;
    }
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::FIRST,
    );
  }

  public function getHeraldName() {
    return pht('New Diff');
  }

}
