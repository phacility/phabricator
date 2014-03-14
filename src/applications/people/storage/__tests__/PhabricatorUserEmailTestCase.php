<?php

final class PhabricatorUserEmailTestCase extends PhabricatorTestCase {

  public function testEmailValidation() {
    $tests = array(
      'alincoln@whitehouse.gov' => true,
      '_-.@.-_' => true,
      '.@.com' => true,
      'user+suffix@gmail.com' => true,
      'IAMIMPORTANT@BUSINESS.COM' => true,
      '1@22.33.44.55' => true,
      '999@999.999' => true,
      'user@2001:0db8:85a3:0042:1000:8a2e:0370:7334' => true,
      '!..!@o.O' => true,

      '' => false,
      str_repeat('a', 256).'@example.com' => false,
      'quack' => false,
      '@gmail.com' => false,
      'usergmail.com' => false,
      '"user" user@gmail.com' => false,
      'a,b@evil.com' => false,
      'a;b@evil.com' => false,
      'ab@evil.com;cd@evil.com' => false,
      'x@y@z.com' => false,
      '@@' => false,
      '@' => false,
      'user@' => false,

      "user@domain.com\n" => false,
      "user@\ndomain.com" => false,
      "\nuser@domain.com" => false,
      "user@domain.com\r" => false,
      "user@\rdomain.com" => false,
      "\ruser@domain.com" => false,
    );

    foreach ($tests as $input => $expect) {
      $actual = PhabricatorUserEmail::isValidAddress($input);
      $this->assertEqual(
        $expect,
        $actual,
        $input);
    }
  }

}
