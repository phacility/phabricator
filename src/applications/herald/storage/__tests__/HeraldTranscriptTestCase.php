<?php

final class HeraldTranscriptTestCase extends PhabricatorTestCase {

  public function testTranscriptTruncation() {
    $long_string = str_repeat('x', 1024 * 1024);
    $short_string = str_repeat('x', 4096)."\n<...>";

    $long_array = array(
      'a' => $long_string,
      'b' => $long_string,
    );

    $mixed_array = array(
      'a' => 'abc',
      'b' => 'def',
      'c' => $long_string,
    );

    $fields = array(
      'ls' => $long_string,
      'la' => $long_array,
      'ma' => $mixed_array,
    );

    $truncated_fields = id(new HeraldObjectTranscript())
      ->setFields($fields)
      ->getFields();

    $this->assertEqual($short_string, $truncated_fields['ls']);

    $this->assertEqual(
      array('a', '<...>'),
      array_keys($truncated_fields['la']));
    $this->assertEqual(
      $short_string.'!<...>',
      implode('!', $truncated_fields['la']));

    $this->assertEqual(
      array('a', 'b', 'c'),
      array_keys($truncated_fields['ma']));
    $this->assertEqual(
      'abc!def!'.substr($short_string, 6),
      implode('!', $truncated_fields['ma']));

  }
}
