<?php

final class PhabricatorMacroTestCase
  extends PhabricatorTestCase {

  public function testMacroNames() {
    $lit = "\xF0\x9F\x94\xA5";
    $combining_diaeresis = "\xCC\x88";

    $cases = array(
      // Only 2 glyphs long.
      "u{$combining_diaeresis}n" => false,
      "{$lit}{$lit}" => false,

      // Too short.
      'a' => false,
      '' => false,

      // Bad characters.
      'yes!' => false,
      "{$lit} {$lit} {$lit}" => false,
      "aaa\nbbb" => false,
      'aaa~' => false,
      'aaa`' => false,

      // Special rejections for only latin symbols.
      '---' => false,
      '___' => false,
      '-_-' => false,
      ':::' => false,
      '-_:' => false,

      "{$lit}{$lit}{$lit}" => true,
      'bwahahaha' => true,
      "u{$combining_diaeresis}nt" => true,
      'a-a-a-a' => true,
    );

    foreach ($cases as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorMacroNameTransaction::isValidMacroName($input),
        pht('Validity of macro "%s"', $input));
    }
  }
}
