<?php

final class PhabricatorUnitsTestCase extends PhabricatorTestCase {

  // NOTE: Keep tests below PHP_INT_MAX on 32-bit systems, since if you write
  // larger numeric literals they'll evaluate to nonsense.

  public function testByteFormatting() {
    $tests = array(
      1                   => '1 B',
      1024                => '1 KB',
      1024 * 1024         => '1 MB',
      10 * 1024 * 1024    => '10 MB',
      100 * 1024 * 1024   => '100 MB',
      1024 * 1024 * 1024  => '1 GB',
      999                 => '999 B',
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        phutil_format_bytes($input),
        'phutil_format_bytes('.$input.')');
    }
  }

  public function testByteParsing() {
    $tests = array(
      '1'             => 1,
      '1k'            => 1024,
      '1K'            => 1024,
      '1kB'           => 1024,
      '1Kb'           => 1024,
      '1KB'           => 1024,
      '1MB'           => 1024 * 1024,
      '1GB'           => 1024 * 1024 * 1024,
      '1.5M'          => (int)(1024 * 1024 * 1.5),
      '1 000'         => 1000,
      '1,234.56 KB'   => (int)(1024 * 1234.56),
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        phutil_parse_bytes($input),
        'phutil_parse_bytes('.$input.')');
    }

    $this->tryTestCases(
      array('string' => 'string'),
      array(false),
      'phutil_parse_bytes');
  }

  public function testDetailedDurationFormatting() {
    $expected_zero = 'now';

    $tests = array (
       12095939 => '19 w, 6 d',
      -12095939 => '19 w, 6 d ago',

        3380521 => '5 w, 4 d',
       -3380521 => '5 w, 4 d ago',

              0 => $expected_zero,
    );

    foreach ($tests as $duration => $expect) {
      $this->assertEqual(
        $expect,
        phutil_format_relative_time_detailed($duration),
        'phutil_format_relative_time_detailed('.$duration.')');
    }


    $tests = array(
      3380521   => array(
        -1 => '5 w',
         0 => '5 w',
         1 => '5 w',
         2 => '5 w, 4 d',
         3 => '5 w, 4 d, 3 h',
         4 => '5 w, 4 d, 3 h, 2 m',
         5 => '5 w, 4 d, 3 h, 2 m, 1 s',
         6 => '5 w, 4 d, 3 h, 2 m, 1 s',
      ),

      -3380521  => array(
        -1 => '5 w ago',
         0 => '5 w ago',
         1 => '5 w ago',
         2 => '5 w, 4 d ago',
         3 => '5 w, 4 d, 3 h ago',
         4 => '5 w, 4 d, 3 h, 2 m ago',
         5 => '5 w, 4 d, 3 h, 2 m, 1 s ago',
         6 => '5 w, 4 d, 3 h, 2 m, 1 s ago',
      ),

      0        => array(
        -1 => $expected_zero,
         0 => $expected_zero,
         1 => $expected_zero,
         2 => $expected_zero,
         3 => $expected_zero,
         4 => $expected_zero,
         5 => $expected_zero,
         6 => $expected_zero,
      ),
    );

    foreach ($tests as $duration => $sub_tests) {
      if (is_array($sub_tests)) {
        foreach ($sub_tests as $levels => $expect) {
          $this->assertEqual(
            $expect,
            phutil_format_relative_time_detailed($duration, $levels),
            'phutil_format_relative_time_detailed('.$duration.',
              '.$levels.')');
        }
      } else {
        $expect = $sub_tests;
        $this->assertEqual(
          $expect,
          phutil_format_relative_time_detailed($duration),
          'phutil_format_relative_time_detailed('.$duration.')');

      }
    }
  }
}
