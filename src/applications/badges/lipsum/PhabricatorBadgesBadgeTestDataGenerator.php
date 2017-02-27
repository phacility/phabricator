<?php

final class PhabricatorBadgesBadgeTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'badges';

  public function getGeneratorName() {
    return pht('Badges');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();

    list($name, $description) = $this->newLoot();

    $xactions = array();

    $xactions[] = array(
      'type' => 'name',
      'value' => $name,
    );

    $xactions[] = array(
      'type' => 'description',
      'value' => $description,
    );

    $params = array(
      'transactions' => $xactions,
    );

    $result = id(new ConduitCall('badges.edit', $params))
      ->setUser($author)
      ->execute();

    return $result['object']['phid'];
  }

  private function newLoot() {
    $drop = id(new PhabricatorBadgesLootContextFreeGrammar())
      ->generate();

    $drop = preg_replace_callback(
      '/<(\d+)-(\d+)>/',
      array($this, 'rollDropValue'),
      $drop);

    $effect_pattern = '/\s*\(([^)]+)\)/';

    $matches = null;
    if (preg_match_all($effect_pattern, $drop, $matches)) {
      $description = $matches[1];
      $description = implode("\n", $description);
    } else {
      $description = '';
    }

    $drop = preg_replace($effect_pattern, '', $drop);

    return array($drop, $description);
  }

  public function rollDropValue($matches) {
    $min = (int)$matches[1];
    $max = (int)$matches[2];
    return mt_rand($min, $max);
  }


}
