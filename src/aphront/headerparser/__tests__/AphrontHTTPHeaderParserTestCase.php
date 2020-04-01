<?php

final class AphrontHTTPHeaderParserTestCase extends PhutilTestCase {

  public function testHeaderParser() {
    $cases = array(
      array(
        'Key: x; y; z',
        'Key',
        'x; y; z',
        array(
          array('x', null),
          array('y', null),
          array('z', null),
        ),
      ),
      array(
        'Content-Disposition: form-data; name="label"',
        'Content-Disposition',
        'form-data; name="label"',
        array(
          array('form-data', null),
          array('name', 'label'),
        ),
      ),
      array(
        'Content-Type: multipart/form-data; charset=utf-8',
        'Content-Type',
        'multipart/form-data; charset=utf-8',
        array(
          array('multipart/form-data', null),
          array('charset', 'utf-8'),
        ),
      ),
      array(
        'Content-Type: application/octet-stream; charset="ut',
        'Content-Type',
        'application/octet-stream; charset="ut',
        false,
      ),
      array(
        'Content-Type: multipart/form-data; boundary=ABCDEFG',
        'Content-Type',
        'multipart/form-data; boundary=ABCDEFG',
        array(
          array('multipart/form-data', null),
          array('boundary', 'ABCDEFG'),
        ),
      ),
      array(
        'Content-Type: multipart/form-data; boundary="ABCDEFG"',
        'Content-Type',
        'multipart/form-data; boundary="ABCDEFG"',
        array(
          array('multipart/form-data', null),
          array('boundary', 'ABCDEFG'),
        ),
      ),
    );

    foreach ($cases as $case) {
      $input = $case[0];
      $expect_name = $case[1];
      $expect_content = $case[2];

      $parser = id(new AphrontHTTPHeaderParser())
        ->parseRawHeader($input);

      $actual_name = $parser->getHeaderName();
      $actual_content = $parser->getHeaderContent();

      $this->assertEqual(
        $expect_name,
        $actual_name,
        pht('Header name for: %s', $input));

      $this->assertEqual(
        $expect_content,
        $actual_content,
        pht('Header content for: %s', $input));

      if (isset($case[3])) {
        $expect_pairs = $case[3];

        $caught = null;
        try {
          $actual_pairs = $parser->getHeaderContentAsPairs();
        } catch (Exception $ex) {
          $caught = $ex;
        }

        if ($expect_pairs === false) {
          $this->assertEqual(
            true,
            ($caught instanceof Exception),
            pht('Expect exception for header pairs of: %s', $input));
        } else {
          $this->assertEqual(
            $expect_pairs,
            $actual_pairs,
            pht('Header pairs for: %s', $input));
        }
      }
    }
  }


}
