<?php

final class QueryFormattingTestCase extends PhabricatorTestCase {

  public function testQueryFormatting() {
    $conn = id(new PhabricatorUser())->establishConnection('r');

    $this->assertEqual(
      'NULL',
      qsprintf($conn, '%nd', null));

    $this->assertEqual(
      '0',
      qsprintf($conn, '%nd', 0));

    $this->assertEqual(
      '0',
      qsprintf($conn, '%d', 0));

    $raised = null;
    try {
      qsprintf($conn, '%d', 'derp');
    } catch (Exception $ex) {
      $raised = $ex;
    }
    $this->assertTrue(
      (bool)$raised,
      pht('%s should raise exception for invalid %%d conversion.', 'qsprintf'));

    $this->assertEqual(
      "'<S>'",
      qsprintf($conn, '%s', null));

    $this->assertEqual(
      'NULL',
      qsprintf($conn, '%ns', null));

    $this->assertEqual(
      "'<S>', '<S>'",
      qsprintf($conn, '%Ls', array('x', 'y')));

    $this->assertEqual(
      "'<B>'",
      qsprintf($conn, '%B', null));

    $this->assertEqual(
      'NULL',
      qsprintf($conn, '%nB', null));

    $this->assertEqual(
      "'<B>', '<B>'",
      qsprintf($conn, '%LB', array('x', 'y')));

    $this->assertEqual(
      '<C>',
      qsprintf($conn, '%T', 'x'));

    $this->assertEqual(
      '<C>',
      qsprintf($conn, '%C', 'y'));

    $this->assertEqual(
      '<C>.<C>',
      qsprintf($conn, '%R', new AphrontDatabaseTableRef('x', 'y')));

  }


}
