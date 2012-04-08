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

final class PhabricatorMetaMTAEmailBodyParserTestCase
  extends PhabricatorTestCase {

  public function testQuotedTextStripping() {
    $bodies = $this->getEmailBodies();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $stripped = $parser->stripTextBody($body);
      $this->assertEqual("OKAY", $stripped);
    }
  }

  private function getEmailBodies() {
    $trailing_space = ' ';

    return array(
<<<EOEMAIL
OKAY

On May 30, 2011, at 8:36 PM, Someone wrote:

> ...

EOEMAIL
,
<<<EOEMAIL
OKAY

On Fri, May 27, 2011 at 9:39 AM, Someone <
somebody@somewhere.com> wrote:

> ...

EOEMAIL
,
<<<EOEMAIL
OKAY

On Fri, May 27, 2011 at 9:39 AM, Someone
<somebody@somewhere.com> wrote:

> ...

EOEMAIL
,
<<<EOEMAIL
OKAY

-----Oprindelig Meddelelse-----

> ...
EOEMAIL
,
<<<EOEMAIL
OKAY

-----Original Message-----

> ...
EOEMAIL
,
<<<EOEMAIL
OKAY

Sent from my HTC smartphone on the Now Network from Sprint!

-Reply message ----- From: "somebody (someone)" <
somebody@somewhere.com>
To: <somebody@somewhere.com>
Subject: Some Text Date: Mon, Apr 2, 2012 1:42 pm
> ...
EOEMAIL
,
<<<EOEMAIL
OKAY

--{$trailing_space}
Abraham Lincoln
Supreme Galactic Emperor
EOEMAIL
,
<<<EOEMAIL
OKAY

Sent from my iPhone
EOEMAIL
,
    );
  }

}
