<?php

final class PhabricatorOwnersPackageTestCase extends PhabricatorTestCase {

  public function testFindLongestPathsPerPackage() {
    $rows = array(
      array(
        'id' => 1,
        'excluded' => 0,
        'dominion' => PhabricatorOwnersPackage::DOMINION_STRONG,
        'path' => 'src/',
      ),
      array(
        'id' => 1,
        'excluded' => 1,
        'dominion' => PhabricatorOwnersPackage::DOMINION_STRONG,
        'path' => 'src/releeph/',
      ),
      array(
        'id' => 2,
        'excluded' => 0,
        'dominion' => PhabricatorOwnersPackage::DOMINION_STRONG,
        'path' => 'src/releeph/',
      ),
    );

    $paths = array(
      'src/' => array('src/a.php' => true, 'src/releeph/b.php' => true),
      'src/releeph/' => array('src/releeph/b.php' => true),
    );
    $this->assertEqual(
      array(
        1 => strlen('src/'),
        2 => strlen('src/releeph/'),
      ),
      PhabricatorOwnersPackage::findLongestPathsPerPackage($rows, $paths));

    $paths = array(
      'src/' => array('src/releeph/b.php' => true),
      'src/releeph/' => array('src/releeph/b.php' => true),
    );
    $this->assertEqual(
      array(
        2 => strlen('src/releeph/'),
      ),
      PhabricatorOwnersPackage::findLongestPathsPerPackage($rows, $paths));


    // Test packages with weak dominion. Here, only package #2 should own the
    // path. Package #1's claim is ceded to Package #2 because it uses weak
    // rules. Package #2 gets the claim even though it also has weak rules
    // because there is no more-specific package.

    $rows = array(
      array(
        'id' => 1,
        'excluded' => 0,
        'dominion' => PhabricatorOwnersPackage::DOMINION_WEAK,
        'path' => 'src/',
      ),
      array(
        'id' => 2,
        'excluded' => 0,
        'dominion' => PhabricatorOwnersPackage::DOMINION_WEAK,
        'path' => 'src/applications/',
      ),
    );

    $pvalue = array('src/applications/main/main.c' => true);

    $paths = array(
      'src/' => $pvalue,
      'src/applications/' => $pvalue,
    );

    $this->assertEqual(
      array(
        2 => strlen('src/applications/'),
      ),
      PhabricatorOwnersPackage::findLongestPathsPerPackage($rows, $paths));


    // Now, add a more specific path to Package #1. This tests nested ownership
    // in packages with weak dominion rules. This time, Package #1 should end
    // up back on top, with Package #2 cedeing control to its more specific
    // path.
    $rows[] = array(
      'id' => 1,
      'excluded' => 0,
      'dominion' => PhabricatorOwnersPackage::DOMINION_WEAK,
      'path' => 'src/applications/main/',
    );

    $paths['src/applications/main/'] = $pvalue;

    $this->assertEqual(
      array(
        1 => strlen('src/applications/main/'),
      ),
      PhabricatorOwnersPackage::findLongestPathsPerPackage($rows, $paths));


  }

}
