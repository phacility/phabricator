<?php

final class PhabricatorTypeaheadDatasourceTestCase
  extends PhabricatorTestCase {

  public function testTypeaheadTokenization() {
    $this->assertTokenization(
      'The quick brown fox',
      array('the', 'quick', 'brown', 'fox'));

    $this->assertTokenization(
      'Quack quack QUACK',
      array('quack'));

    $this->assertTokenization(
      '',
      array());

    $this->assertTokenization(
      '  [ - ]  ',
      array());

    $this->assertTokenization(
      'jury-rigged',
      array('jury', 'rigged'));

    $this->assertTokenization(
      '[[ brackets ]] [-] ]-[ tie-fighters',
      array('brackets', 'tie', 'fighters'));

    $this->assertTokenization(
      'viewer()',
      array('viewer'));

    $this->assertTokenization(
      'Work (Done)',
      array('work', 'done'));

    $this->assertTokenization(
      'A (B C D)',
      array('a', 'b', 'c', 'd'));
  }

  private function assertTokenization($input, $expect) {
    $this->assertEqual(
      $expect,
      PhabricatorTypeaheadDatasource::tokenizeString($input),
      pht('Tokenization of "%s"', $input));
  }

}
