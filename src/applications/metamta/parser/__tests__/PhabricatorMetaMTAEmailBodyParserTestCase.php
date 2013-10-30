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

  public function testEmailBodyCommandParsing() {
    $bodies = $this->getEmailBodiesWithFullCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual('OKAY', $body_data['body']);
      $this->assertEqual('whatevs', $body_data['command']);
      $this->assertEqual('dude', $body_data['command_value']);
    }
    $bodies = $this->getEmailBodiesWithPartialCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual('OKAY', $body_data['body']);
      $this->assertEqual('whatevs', $body_data['command']);
      $this->assertEqual(null, $body_data['command_value']);
    }
  }

  private function getEmailBodiesWithFullCommands() {
    $bodies = $this->getEmailBodies();
    $with_commands = array();
    foreach ($bodies as $body) {
      $with_commands[] = "!whatevs dude\n" . $body;
    }
    return $with_commands;
  }

  private function getEmailBodiesWithPartialCommands() {
    $bodies = $this->getEmailBodies();
    $with_commands = array();
    foreach ($bodies as $body) {
      $with_commands[] = "!whatevs\n" . $body;
    }
    return $with_commands;
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
<<<EOMAIL
OKAY

________________________________________
From: Abraham Lincoln <alincoln@logcab.in>
Subject: Core World Tariffs
EOMAIL
,
<<<EOMAIL
OKAY

> On 17 Oct 2013, at 17:47, "Someone" <somebody@somewhere> wrote:
> ...
EOMAIL
    );
  }

}
