<?php

final class DiffusionGitFileContentQueryTestCase extends PhabricatorTestCase {

  public function testAuthorName() {
    // A normal case - no parenthesis in user name
    $result = DiffusionGitFileContentQuery::match(
      '8220d5d54f6d5d5552a636576cbe9c35f15b65b2 '.
      '(Andrew Gallagher       2010-12-03      324) $somevar = $this->call()');
    $this->assertEqual($result[0], '8220d5d54f6d5d5552a636576cbe9c35f15b65b2');
    $this->assertEqual($result[1], 'Andrew Gallagher');
    $this->assertEqual($result[2], ' $somevar = $this->call()');

    // User name like 'Jimmy (He) Zhang'
    $result = DiffusionGitFileContentQuery::match(
      '8220d5d54f6d5d5552a636576cbe9c35f15b65b2 '.
      '( Jimmy (He) Zhang    2013-10-11    5) '.
      'code(); "(string literal 9999-99-99 2)"; more_code();');
    $this->assertEqual($result[1], 'Jimmy (He) Zhang');
    $this->assertEqual($result[2],
      ' code(); "(string literal 9999-99-99 2)"; more_code();');

    // User name like 'Scott Shapiro (Ads Product Marketing)'
    $result = DiffusionGitFileContentQuery::match(
      '8220d5d54f6d5d5552a636576cbe9c35f15b65b2 '.
      '( Scott Shapiro (Ads Product Marketing)    2013-10-11    5) '.
      'code(); "(string literal 9999-99-99 2)"; more_code();');
    $this->assertEqual($result[1], 'Scott Shapiro (Ads Product Marketing)');
    $this->assertEqual($result[2],
      ' code(); "(string literal 9999-99-99 2)"; more_code();');
  }
}
