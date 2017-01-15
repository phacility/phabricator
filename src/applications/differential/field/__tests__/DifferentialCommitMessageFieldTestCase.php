<?php

final class DifferentialCommitMessageFieldTestCase
  extends PhabricatorTestCase {

  public function testRevisionCommitMessageFieldParsing() {
    $base_uri = 'https://www.example.com/';

    $tests = array(
      'D123' => 123,
      'd123' => 123,
      "  \n  d123 \n " => 123,
      "D123\nSome-Custom-Field: The End" => 123,
      "{$base_uri}D123" => 123,
      "{$base_uri}D123\nSome-Custom-Field: The End" => 123,
    );

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.base-uri', $base_uri);

    foreach ($tests as $input => $expect) {
      $actual = id(new DifferentialRevisionIDCommitMessageField())
        ->parseFieldValue($input);
      $this->assertEqual($expect, $actual, pht('Parse of: %s', $input));
    }

    unset($env);
  }

}
