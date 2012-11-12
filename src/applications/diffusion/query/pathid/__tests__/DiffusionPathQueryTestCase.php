<?php

final class DiffusionPathQueryTestCase extends PhabricatorTestCase {

  public function testParentEdgeCases() {
    $this->assertEqual(
      '/',
      DiffusionPathIDQuery::getParentPath('/'),
      'Parent of /');
    $this->assertEqual(
      '/',
      DiffusionPathIDQuery::getParentPath('x.txt'),
      'Parent of x.txt');
    $this->assertEqual(
      '/a',
      DiffusionPathIDQuery::getParentPath('/a/b'),
      'Parent of /a/b');
    $this->assertEqual(
      '/a',
      DiffusionPathIDQuery::getParentPath('/a///b'),
      'Parent of /a///b');
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

