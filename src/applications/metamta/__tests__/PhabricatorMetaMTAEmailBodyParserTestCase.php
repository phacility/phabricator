<?php

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

-----oprindelig meddelelse-----

> ...
EOEMAIL
,
<<<EOEMAIL
OKAY

-----original message-----

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
