<?php

final class AphrontRequestTestCase extends PhabricatorTestCase {

  public function testRequestDataAccess() {
    $r = new AphrontRequest('example.com', '/');
    $r->setRequestData(
      array(
        'str_empty' => '',
        'str'       => 'derp',
        'str_true'  => 'true',
        'str_false' => 'false',

        'zero'      => '0',
        'one'       => '1',

        'arr_empty' => array(),
        'arr_num'   => array(1, 2, 3),

        'comma'     => ',',
        'comma_1'   => 'a, b',
        'comma_2'   => ' ,a ,, b ,,,, ,, ',
        'comma_3'   => '0',
        'comma_4'   => 'a, a, b, a',
        'comma_5'   => "a\nb, c\n\nd\n\n\n,\n",
    ));

    $this->assertEqual(1, $r->getInt('one'));
    $this->assertEqual(0, $r->getInt('zero'));
    $this->assertEqual(null, $r->getInt('does-not-exist'));
    $this->assertEqual(0, $r->getInt('str_empty'));

    $this->assertEqual(true, $r->getBool('one'));
    $this->assertEqual(false, $r->getBool('zero'));
    $this->assertEqual(true, $r->getBool('str_true'));
    $this->assertEqual(false, $r->getBool('str_false'));
    $this->assertEqual(true, $r->getBool('str'));
    $this->assertEqual(null, $r->getBool('does-not-exist'));
    $this->assertEqual(false, $r->getBool('str_empty'));

    $this->assertEqual('derp', $r->getStr('str'));
    $this->assertEqual('', $r->getStr('str_empty'));
    $this->assertEqual(null, $r->getStr('does-not-exist'));

    $this->assertEqual(array(), $r->getArr('arr_empty'));
    $this->assertEqual(array(1, 2, 3), $r->getArr('arr_num'));
    $this->assertEqual(null, $r->getArr('str_empty', null));
    $this->assertEqual(null, $r->getArr('str_true', null));
    $this->assertEqual(null, $r->getArr('does-not-exist', null));
    $this->assertEqual(array(), $r->getArr('does-not-exist'));

    $this->assertEqual(array(), $r->getStrList('comma'));
    $this->assertEqual(array('a', 'b'), $r->getStrList('comma_1'));
    $this->assertEqual(array('a', 'b'), $r->getStrList('comma_2'));
    $this->assertEqual(array('0'), $r->getStrList('comma_3'));
    $this->assertEqual(array('a', 'a', 'b', 'a'), $r->getStrList('comma_4'));
    $this->assertEqual(array('a', 'b', 'c', 'd'), $r->getStrList('comma_5'));
    $this->assertEqual(array(), $r->getStrList('does-not-exist'));
    $this->assertEqual(null, $r->getStrList('does-not-exist', null));

    $this->assertEqual(true, $r->getExists('str'));
    $this->assertEqual(false, $r->getExists('does-not-exist'));
  }

  public function testHostAttacks() {
    static $tests = array(
      'domain.com'                    => 'domain.com',
      'domain.com:80'                 => 'domain.com',
      'evil.com:evil.com@real.com'    => 'real.com',
      'evil.com:evil.com@real.com:80' => 'real.com',
    );

    foreach ($tests as $input => $expect) {
      $r = new AphrontRequest($input, '/');
      $this->assertEqual(
        $expect,
        $r->getHost(),
        pht('Host: %s', $input));
    }
  }

  public function testFlattenRequestData() {
    $test_cases = array(
      array(
        'a' => 'a',
        'b' => '1',
        'c' => '',
      ),
      array(
        'a' => 'a',
        'b' => '1',
        'c' => '',
      ),

      array(
        'x' => array(
          0 => 'a',
          1 => 'b',
          2 => 'c',
        ),
      ),
      array(
        'x[0]' => 'a',
        'x[1]' => 'b',
        'x[2]' => 'c',
      ),

      array(
        'x' => array(
          'y' => array(
            'z' => array(
              40 => 'A',
              50 => 'B',
              'C' => 60,
            ),
          ),
        ),
      ),
      array(
        'x[y][z][40]' => 'A',
        'x[y][z][50]' => 'B',
        'x[y][z][C]'  => '60',
      ),
    );

    for ($ii = 0; $ii < count($test_cases); $ii += 2) {
      $input  = $test_cases[$ii];
      $expect = $test_cases[$ii + 1];

      $this->assertEqual($expect, AphrontRequest::flattenData($input));
    }
  }

  public function testGetHTTPHeader() {
    $server_data = array(
      'HTTP_ACCEPT_ENCODING' => 'duck/quack',
      'CONTENT_TYPE' => 'cow/moo',
    );

    $this->assertEqual(
      'duck/quack',
      AphrontRequest::getHTTPHeader('AcCePt-EncOdING', null, $server_data));
    $this->assertEqual(
      'cow/moo',
      AphrontRequest::getHTTPHeader('cONTent-TyPE', null, $server_data));
    $this->assertEqual(
      null,
      AphrontRequest::getHTTPHeader('Pie-Flavor', null, $server_data));
  }

}
