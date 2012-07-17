<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group metamta
 */
final class PhabricatorMetaMTAMailBodyTestCase extends PhabricatorTestCase {

  public function testBodyRender() {

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.base-uri', 'http://test.com/');

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection("salmon");
    $body->addTextSection("HEADER", "bass\ntrout\n");
    $body->addHeraldSection("/rules/", "/xscript/");
    $body->addReplySection("pike");

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

    $this->assertEqual($expect, $body->render());
  }


}
