<?php

final class PhutilPygmentizeParserTestCase extends PhutilTestCase {

  public function testPygmentizeParser() {
    $this->tryParser(
      '',
      '',
      array(),
      pht('Empty'));

    $this->tryParser(
      '<span class="mi">1</span>',
      '<span style="color: #ff0000">1</span>',
      array(
        'mi' => 'color: #ff0000',
      ),
      pht('Simple'));

    $this->tryParser(
      '<span class="mi">1</span>',
      '<span class="mi">1</span>',
      array(),
      pht('Missing Class'));

    $this->tryParser(
      '<span data-symbol-name="X" class="nc">X</span>',
      '<span data-symbol-name="X" style="color: #ff0000">X</span>',
      array(
        'nc' => 'color: #ff0000',
      ),
      pht('Extra Attribute'));
  }

  private function tryParser($input, $expect, array $map, $label) {
    $actual = id(new PhutilPygmentizeParser())
      ->setMap($map)
      ->parse($input);

    $this->assertEqual($expect, $actual, pht('Pygmentize Parser: %s', $label));
  }

}
