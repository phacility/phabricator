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

  public function testFunctionEvaluation() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $datasource = id(new PhabricatorTypeaheadTestNumbersDatasource())
      ->setViewer($viewer);

    $constraints = $datasource->evaluateTokens(
      array(
        9,
        'seven()',
        12,
        3,
      ));

    $this->assertEqual(
      array(9, 7, 12, 3),
      $constraints);

    $map = array(
      'inc(3)' => 4,
      'sum(3, 4)' => 7,
      'inc(seven())' => 8,
      'inc(inc(3))' => 5,
      'inc(inc(seven()))' => 9,
      'sum(seven(), seven())' => 14,
      'sum(inc(seven()), inc(inc(9)))' => 19,
    );

    foreach ($map as $input => $expect) {
      $constraints = $datasource->evaluateTokens(
        array(
          $input,
        ));

      $this->assertEqual(
        array($expect),
        $constraints,
        pht('Constraints for input "%s".', $input));
    }
  }


}
