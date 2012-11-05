<?php

final class HeraldDryRunAdapter extends HeraldObjectAdapter {

  public function getPHID() {
    return 0;
  }

  public function getHeraldName() {
    return 'Dry Run';
  }

  public function getHeraldTypeName() {
    return null;
  }

  public function getHeraldField($field) {
    return null;
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');
    $results = array();
    foreach ($effects as $effect) {
      $results[] = new HeraldApplyTranscript(
        $effect,
        false,
        'This was a dry run, so no actions were actually taken.');
    }
    return $results;
  }
}
