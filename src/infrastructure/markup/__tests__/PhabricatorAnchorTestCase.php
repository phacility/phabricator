<?php

final class PhabricatorAnchorTestCase
  extends PhabricatorTestCase {

  public function testAnchors() {

    $low_ascii = '';
    for ($ii = 19; $ii <= 127; $ii++) {
      $low_ascii .= chr($ii);
    }

    $snowman = "\xE2\x9B\x84";

    $map = array(
      '' => '',
      'Bells and Whistles' => 'bells-and-whistles',
      'Termination for Nonpayment' => 'termination-for-nonpayment',
      $low_ascii => '0123456789-abcdefghijklmnopqrstu',
      'xxxx xxxx xxxx xxxx xxxx on' => 'xxxx-xxxx-xxxx-xxxx-xxxx',
      'xxxx xxxx xxxx xxxx xxxx ox' => 'xxxx-xxxx-xxxx-xxxx-xxxx-ox',
      "So, You Want To Build A {$snowman}?" =>
        "so-you-want-to-build-a-{$snowman}",
      str_repeat($snowman, 128) => str_repeat($snowman, 32),
    );

    foreach ($map as $input => $expect) {
      $anchor = PhutilRemarkupHeaderBlockRule::getAnchorNameFromHeaderText(
        $input);

      $this->assertEqual(
        $expect,
        $anchor,
        pht('Anchor for "%s".', $input));
    }
  }

}
