<?php

final class AphrontMultipartParserTestCase extends PhutilTestCase {

  public function testParser() {
    $map = array(
      array(
        'data' => 'simple.txt',
        'variables' => array(
          array('a', 'b'),
        ),
      ),
    );

    $data_dir = dirname(__FILE__).'/data/';
    foreach ($map as $test_case) {
      $data = Filesystem::readFile($data_dir.$test_case['data']);
      $data = str_replace("\n", "\r\n", $data);

      $parser = id(new AphrontMultipartParser())
        ->setContentType('multipart/form-data; boundary=ABCDEFG');
      $parser->beginParse();
      $parser->continueParse($data);
      $parts = $parser->endParse();

      $variables = array();
      foreach ($parts as $part) {
        if (!$part->isVariable()) {
          continue;
        }

        $variables[] = array(
          $part->getName(),
          $part->getVariableValue(),
        );
      }

      $expect_variables = idx($test_case, 'variables', array());
      $this->assertEqual($expect_variables, $variables);
    }
  }



}
