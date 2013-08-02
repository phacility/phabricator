<?php

final class HeraldDryRunAdapter extends HeraldAdapter {

  public function getPHID() {
    return 0;
  }

  public function isEnabled() {
    return false;
  }

  public function getAdapterContentName() {
    return null;
  }

  public function getHeraldName() {
    return 'Dry Run';
  }

  public function getHeraldField($field) {
    return null;
  }

  public function getFields() {
    return array();
  }

  public function getActions($rule_type) {
    return array();
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');
    $results = array();
    foreach ($effects as $effect) {
      $results[] = new HeraldApplyTranscript(
        $effect,
        false,
        pht('This was a dry run, so no actions were actually taken.'));
    }
    return $results;
  }
}
