<?php

final class DifferentialRevisionIDFieldParserTestCase
  extends PhabricatorTestCase {

  public function testFieldParser() {

    $this->assertEqual(
      null,
      $this->parse('123'));

    $this->assertEqual(
      null,
      $this->parse('D123'));

    // NOTE: We expect foreign, validly-formatted URIs to be ignored.
    $this->assertEqual(
      null,
      $this->parse('http://phabricator.example.com/D123'));

    $this->assertEqual(
      123,
      $this->parse(PhabricatorEnv::getProductionURI('/D123')));

  }

  private function parse($value) {
    return DifferentialRevisionIDFieldSpecification::parseRevisionIDFromURI(
      $value);
  }

}
