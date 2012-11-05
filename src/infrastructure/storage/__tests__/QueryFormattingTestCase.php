<?php

final class QueryFormattingTestCase extends PhabricatorTestCase {

  public function testQueryFormatting() {
    $conn_r = id(new PhabricatorUser())->establishConnection('r');

    $this->assertEqual(
      'NULL',
      qsprintf($conn_r, '%nd', null));

    $this->assertEqual(
      '0',
      qsprintf($conn_r, '%nd', 0));

    $this->assertEqual(
      '0',
      qsprintf($conn_r, '%d', 0));

    $raised = null;
    try {
      qsprintf($conn_r, '%d', 'derp');
    } catch (Exception $ex) {
      $raised = $ex;
    }
    $this->assertEqual(
      (bool)$raised,
      true,
      'qsprintf should raise exception for invalid %d conversion.');

    $this->assertEqual(
      "'<S>'",
      qsprintf($conn_r, '%s', null));

    $this->assertEqual(
      'NULL',
      qsprintf($conn_r, '%ns', null));
  }

}
