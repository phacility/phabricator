<?php

final class PhabricatorOwnersPackageTestCase extends PhabricatorTestCase {

  public function testFindLongestPathsPerPackage() {
    $rows = array(
      array('id' => 1, 'excluded' => 0, 'path' => 'src/'),
      array('id' => 1, 'excluded' => 1, 'path' => 'src/releeph/'),
      array('id' => 2, 'excluded' => 0, 'path' => 'src/releeph/'),
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
  }

}
