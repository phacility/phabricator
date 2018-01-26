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
      'dog',
      'cat',
      'rat',
      'bat',
      'dig',
      'fig',
      'cot',
      'cut',
      'fog',
      'rig',
      'rug',
      'dug',
      'mat',
      'pat',
      'eat',
      'tar',
      'pot',
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

  public function testHashForAnchor() {
    $map = array(
      // For inputs with no "." or "_" in the output, digesting for an index
      // or an anchor should be the same.
      'dog' => array(
        'Aliif7Qjd5ct',
        'Aliif7Qjd5ct',
      ),
      // When an output would contain "." or "_", it should be replaced with
      // an alphanumeric character in those positions instead.
      'fig' => array(
        'OpB9tY4i.MOX',
        'OpB9tY4imMOX',
      ),
      'cot' => array(
        '_iF26XU_PsVY',
        '3iF26XUkPsVY',
      ),
      // The replacement characters are well-distributed and generally keep
      // the entropy of the output high: here, "_" characters in the initial
      // positions of the digests of "cot" (above) and "dug" (this test) have
      // different outputs.
      'dug' => array(
        '_XuQnp0LUlUW',
        '7XuQnp0LUlUW',
      ),
    );

    foreach ($map as $input => $expect) {
      list($expect_index, $expect_anchor) = $expect;

      $this->assertEqual(
        $expect_index,
        PhabricatorHash::digestForIndex($input),
        pht('Index digest of "%s".', $input));

      $this->assertEqual(
        $expect_anchor,
        PhabricatorHash::digestForAnchor($input),
        pht('Anchor digest of "%s".', $input));
    }
  }

}
