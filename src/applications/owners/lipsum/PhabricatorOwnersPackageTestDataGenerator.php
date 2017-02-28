<?php

final class PhabricatorOwnersPackageTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'owners';

  public function getGeneratorName() {
    return pht('Owners Packages');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();

    $name = id(new PhabricatorOwnersPackageContextFreeGrammar())
      ->generate();

    switch ($this->roll(1, 4)) {
      case 1:
      case 2:
        // Most packages own only one path.
        $path_count = 1;
        break;
      case 3:
        // Some packages own a few paths.
        $path_count = mt_rand(1, 4);
        break;
      case 4:
        // Some packages own a very large number of paths.
        $path_count = mt_rand(1, 1024);
        break;
    }

    $xactions = array();

    $xactions[] = array(
      'type' => 'name',
      'value' => $name,
    );

    $xactions[] = array(
      'type' => 'owners',
      'value' => array($author->getPHID()),
    );

    $dominion = PhabricatorOwnersPackage::getDominionOptionsMap();
    $dominion = array_rand($dominion);
    $xactions[] = array(
      'type' => 'dominion',
      'value' => $dominion,
    );

    $paths = id(new PhabricatorOwnersPathContextFreeGrammar())
      ->generateSeveral($path_count, "\n");
    $paths = explode("\n", $paths);
    $paths = array_unique($paths);

    $repository_phid = $this->loadOneRandom('PhabricatorRepository')
      ->getPHID();

    $paths_value = array();
    foreach ($paths as $path) {
      $paths_value[] = array(
        'repositoryPHID' => $repository_phid,
        'path' => $path,

        // Excluded paths are relatively rare.
        'excluded' => (mt_rand(1, 10) == 1),
      );
    }

    $xactions[] = array(
      'type' => 'paths.set',
      'value' => $paths_value,
    );

    $params = array(
      'transactions' => $xactions,
    );

    $result = id(new ConduitCall('owners.edit', $params))
      ->setUser($author)
      ->execute();

    return $result['object']['phid'];
  }


}
