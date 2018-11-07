<?php

final class QueryFormattingTestCase extends PhabricatorTestCase {

  public function testQueryFormatting() {
    $conn = id(new PhabricatorUser())->establishConnection('r');

    $this->assertEqual(
      'NULL',
      (string)qsprintf($conn, '%nd', null));

    $this->assertEqual(
      '0',
      (string)qsprintf($conn, '%nd', 0));

    $this->assertEqual(
      '0',
      (string)qsprintf($conn, '%d', 0));

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
      (string)qsprintf($conn, '%s', null));

    $this->assertEqual(
      'NULL',
      (string)qsprintf($conn, '%ns', null));

    $this->assertEqual(
      "'<S>', '<S>'",
      (string)qsprintf($conn, '%Ls', array('x', 'y')));

    $this->assertEqual(
      "'<B>'",
      (string)qsprintf($conn, '%B', null));

    $this->assertEqual(
      'NULL',
      (string)qsprintf($conn, '%nB', null));

    $this->assertEqual(
      "'<B>', '<B>'",
      (string)qsprintf($conn, '%LB', array('x', 'y')));

    $this->assertEqual(
      '<C>',
      (string)qsprintf($conn, '%T', 'x'));

    $this->assertEqual(
      '<C>',
      (string)qsprintf($conn, '%C', 'y'));

    $this->assertEqual(
      '<C>.<C>',
      (string)qsprintf($conn, '%R', new AphrontDatabaseTableRef('x', 'y')));

  }


}
