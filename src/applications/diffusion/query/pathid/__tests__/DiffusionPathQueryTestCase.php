<?php

final class DiffusionPathQueryTestCase extends PhabricatorTestCase {

  public function testParentEdgeCases() {
    $this->assertEqual(
      '/',
      DiffusionPathIDQuery::getParentPath('/'),
      pht('Parent of %s', '/'));
    $this->assertEqual(
      '/',
      DiffusionPathIDQuery::getParentPath('x.txt'),
      pht('Parent of %s', 'x.txt'));
    $this->assertEqual(
      '/a',
      DiffusionPathIDQuery::getParentPath('/a/b'),
      pht('Parent of %s', '/a/b'));
    $this->assertEqual(
      '/a',
      DiffusionPathIDQuery::getParentPath('/a///b'),
      pht('Parent of %s', '/a///b'));
  }

  public function testExpandEdgeCases() {
    $this->assertEqual(
      array('/'),
      DiffusionPathIDQuery::expandPathToRoot('/'));
    $this->assertEqual(
      array('/'),
      DiffusionPathIDQuery::expandPathToRoot('//'));
    $this->assertEqual(
      array('/a/b', '/a', '/'),
      DiffusionPathIDQuery::expandPathToRoot('/a/b'));
    $this->assertEqual(
      array('/a/b', '/a', '/'),
      DiffusionPathIDQuery::expandPathToRoot('/a//b'));
    $this->assertEqual(
      array('/a/b', '/a', '/'),
      DiffusionPathIDQuery::expandPathToRoot('a/b'));
  }

}
