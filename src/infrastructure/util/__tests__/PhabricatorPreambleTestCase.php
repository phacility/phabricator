<?php

final class PhabricatorPreambleTestCase
  extends PhabricatorTestCase {

  /**
   * @phutil-external-symbol function preamble_get_x_forwarded_for_address
   */
  public function testXForwardedForLayers() {
    $tests = array(
      // This is normal behavior with one load balancer.
      array(
        'header' => '1.2.3.4',
        'layers' => 1,
        'expect' => '1.2.3.4',
      ),

      // In this case, the LB received a request which already had an
      // "X-Forwarded-For" header. This might be legitimate (in the case of
      // a CDN request) or illegitimate (in the case of a client making
      // things up). We don't want to trust it.
      array(
        'header' => '9.9.9.9, 1.2.3.4',
        'layers' => 1,
        'expect' => '1.2.3.4',
      ),

      // Multiple layers of load balancers.
      array(
        'header' => '9.9.9.9, 1.2.3.4',
        'layers' => 2,
        'expect' => '9.9.9.9',
      ),

      // Multiple layers of load balancers, plus a client-supplied value.
      array(
        'header' => '8.8.8.8, 9.9.9.9, 1.2.3.4',
        'layers' => 2,
        'expect' => '9.9.9.9',
      ),

      // Multiple layers of load balancers, but this request came from
      // somewhere inside the network.
      array(
        'header' => '1.2.3.4',
        'layers' => 2,
        'expect' => '1.2.3.4',
      ),

      array(
        'header' => 'A, B, C, D, E, F, G, H, I',
        'layers' => 7,
        'expect' => 'C',
      ),
    );

    foreach ($tests as $test) {
      $header = $test['header'];
      $layers = $test['layers'];
      $expect = $test['expect'];

      $actual = preamble_get_x_forwarded_for_address($header, $layers);

      $this->assertEqual(
        $expect,
        $actual,
        pht(
          'Address after stripping %d layers from: %s',
          $layers,
          $header));
    }
  }

}
