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
        'path' => 'src/example/',
      ),
      array(
        'id' => 2,
        'excluded' => 0,
        'dominion' => PhabricatorOwnersPackage::DOMINION_STRONG,
        'path' => 'src/example/',
      ),
    );

    $paths = array(
      'src/' => array('src/a.php' => true, 'src/example/b.php' => true),
      'src/example/' => array('src/example/b.php' => true),
    );
    $this->assertEqual(
      array(
        1 => strlen('src/'),
        2 => strlen('src/example/'),
      ),
      PhabricatorOwnersPackage::findLongestPathsPerPackage($rows, $paths));

    $paths = array(
      'src/' => array('src/example/b.php' => true),
      'src/example/' => array('src/example/b.php' => true),
    );
    $this->assertEqual(
      array(
        2 => strlen('src/example/'),
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
    // up back on top, with Package #2 ceding control to its more specific
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


    // Test cases where multiple packages own the same path, with various
    // dominion rules.

    $main_c = 'src/applications/main/main.c';

    $rules = array(
      // All claims strong.
      array(
        PhabricatorOwnersPackage::DOMINION_STRONG,
        PhabricatorOwnersPackage::DOMINION_STRONG,
        PhabricatorOwnersPackage::DOMINION_STRONG,
      ),
      // All claims weak.
      array(
        PhabricatorOwnersPackage::DOMINION_WEAK,
        PhabricatorOwnersPackage::DOMINION_WEAK,
        PhabricatorOwnersPackage::DOMINION_WEAK,
      ),
      // Mixture of strong and weak claims, strong first.
      array(
        PhabricatorOwnersPackage::DOMINION_STRONG,
        PhabricatorOwnersPackage::DOMINION_STRONG,
        PhabricatorOwnersPackage::DOMINION_WEAK,
      ),
      // Mixture of strong and weak claims, weak first.
      array(
        PhabricatorOwnersPackage::DOMINION_WEAK,
        PhabricatorOwnersPackage::DOMINION_STRONG,
        PhabricatorOwnersPackage::DOMINION_STRONG,
      ),
    );

    foreach ($rules as $rule_idx => $rule) {
      $rows = array(
        array(
          'id' => 1,
          'excluded' => 0,
          'dominion' => $rule[0],
          'path' => $main_c,
        ),
        array(
          'id' => 2,
          'excluded' => 0,
          'dominion' => $rule[1],
          'path' => $main_c,
        ),
        array(
          'id' => 3,
          'excluded' => 0,
          'dominion' => $rule[2],
          'path' => $main_c,
        ),
      );

      $paths = array(
        $main_c => $pvalue,
      );

      // If one or more packages have strong dominion, they should own the
      // path. If not, all the packages with weak dominion should own the
      // path.
      $strong = array();
      $weak = array();
      foreach ($rule as $idx => $dominion) {
        if ($dominion == PhabricatorOwnersPackage::DOMINION_STRONG) {
          $strong[] = $idx + 1;
        } else {
          $weak[] = $idx + 1;
        }
      }

      if ($strong) {
        $expect = $strong;
      } else {
        $expect = $weak;
      }

      $expect = array_fill_keys($expect, strlen($main_c));
      $actual = PhabricatorOwnersPackage::findLongestPathsPerPackage(
        $rows,
        $paths);

      ksort($actual);

      $this->assertEqual(
        $expect,
        $actual,
        pht('Ruleset "%s" for Identical Ownership', $rule_idx));
    }
  }

}
