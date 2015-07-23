<?php

final class PhabricatorMetaMTAMailBodyTestCase extends PhabricatorTestCase {

  public function testBodyRender() {
    $expect = <<<EOTEXT
salmon

HEADER
  bass
  trout

WHY DID I GET THIS EMAIL?
  http://test.com/xscript/

EOTEXT;

    $this->assertEmail($expect, true);
  }

  public function testBodyRenderNoHerald() {
    $expect = <<<EOTEXT
salmon

HEADER
  bass
  trout

EOTEXT;

    $this->assertEmail($expect, false);
  }

  private function assertEmail($expect, $herald_hints) {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.production-uri', 'http://test.com/');
    $env->overrideEnvConfig('metamta.herald.show-hints', $herald_hints);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection('salmon');
    $body->addTextSection('HEADER', "bass\ntrout\n");
    $body->addHeraldSection('/xscript/');

    $this->assertEqual($expect, $body->render());
  }

}
