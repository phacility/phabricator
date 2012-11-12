<?php

/**
 * @group metamta
 */
final class PhabricatorMetaMTAMailBodyTestCase extends PhabricatorTestCase {


  public function testBodyRender() {
    $expect = <<<EOTEXT
salmon

HEADER
  bass
  trout

MANAGE HERALD RULES
  http://test.com/rules/

WHY DID I GET THIS EMAIL?
  http://test.com/xscript/

REPLY HANDLER ACTIONS
  pike

EOTEXT;

    $this->assertEmail($expect, true, true);
  }


  public function testBodyRenderNoHerald() {
    $expect = <<<EOTEXT
salmon

HEADER
  bass
  trout

REPLY HANDLER ACTIONS
  pike

EOTEXT;

    $this->assertEmail($expect, false, true);
  }


  public function testBodyRenderNoReply() {
    $expect = <<<EOTEXT
salmon

HEADER
  bass
  trout

MANAGE HERALD RULES
  http://test.com/rules/

WHY DID I GET THIS EMAIL?
  http://test.com/xscript/

EOTEXT;

    $this->assertEmail($expect, true, false);
  }

  private function assertEmail($expect, $herald_hints, $reply_hints) {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.production-uri', 'http://test.com/');
    $env->overrideEnvConfig('metamta.herald.show-hints', $herald_hints);
    $env->overrideEnvConfig('metamta.reply.show-hints', $reply_hints);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection("salmon");
    $body->addTextSection("HEADER", "bass\ntrout\n");
    $body->addHeraldSection("/rules/", "/xscript/");
    $body->addReplySection("pike");

    $this->assertEqual($expect, $body->render());
  }


}
