<?php

final class PhutilSafeHTMLTestCase extends PhutilTestCase {

  public function testOperator() {
    if (!extension_loaded('operator')) {
      $this->assertSkipped(pht('Operator extension not available.'));
    }

    $a = phutil_tag('a');
    $ab = $a.phutil_tag('b');
    $this->assertEqual('<a></a><b></b>', $ab->getHTMLContent());
    $this->assertEqual('<a></a>', $a->getHTMLContent());

    $a .= phutil_tag('a');
    $this->assertEqual('<a></a><a></a>', $a->getHTMLContent());
  }

}
