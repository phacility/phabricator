<?php

final class PhabricatorHashTestCase extends PhabricatorTestCase {

  public function testHashForIndex() {
    $map = array(
      'dog' => 'Aliif7Qjd5ct',
      'cat' => 'toudDsue3Uv8',
      'rat' => 'RswaKgTjqOuj',
      'bat' => 'rAkJKyX4YdYm',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorHash::digestForIndex($input),
        pht('Input: %s', $input));
    }

    // Test that the encoding produces 6 bits of entropy per byte.
    $entropy = array(
      'dog', 'cat', 'rat', 'bat', 'dig', 'fig', 'cot',
      'cut', 'fog', 'rig', 'rug', 'dug', 'mat', 'pat',
      'eat', 'tar', 'pot',
    );

    $seen = array();
    foreach ($entropy as $input) {
      $chars = preg_split('//', PhabricatorHash::digestForIndex($input));
      foreach ($chars as $char) {
        $seen[$char] = true;
      }
    }

    $this->assertEqual(
      (1 << 6),
      count($seen),
      pht('Distinct characters in hash of: %s', $input));
  }

}
