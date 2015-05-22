<?php

final class PhabricatorMetaMTAEmailBodyParserTestCase
  extends PhabricatorTestCase {

  public function testQuotedTextStripping() {
    $bodies = $this->getEmailBodies();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $stripped = $parser->stripTextBody($body);
      $this->assertEqual('OKAY', $stripped);
    }
  }

  public function testEmailBodyCommandParsing() {
    $bodies = $this->getEmailBodiesWithFullCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual('OKAY', $body_data['body']);
      $this->assertEqual(
        array(
          array('whatevs', 'dude'),
        ),
        $body_data['commands']);
    }

    $bodies = $this->getEmailBodiesWithPartialCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual('OKAY', $body_data['body']);
      $this->assertEqual(
        array(
          array('whatevs'),
        ),
        $body_data['commands']);
    }

    $bodies = $this->getEmailBodiesWithMultipleCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual("preface\n\nOKAY", $body_data['body']);
      $this->assertEqual(
        array(
          array('top1'),
          array('top2'),
        ),
        $body_data['commands']);
    }

    $bodies = $this->getEmailBodiesWithSplitCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual('OKAY', $body_data['body']);
      $this->assertEqual(
        array(
          array('cmd1'),
          array('cmd2'),
        ),
        $body_data['commands']);
    }

    $bodies = $this->getEmailBodiesWithMiddleCommands();
    foreach ($bodies as $body) {
      $parser = new PhabricatorMetaMTAEmailBodyParser();
      $body_data = $parser->parseBody($body);
      $this->assertEqual("HEAD\n!cmd2\nTAIL", $body_data['body']);
    }
  }

  public function testFalsePositiveForOnWrote() {
    $body = <<<EOEMAIL
On which horse shall you ride?

On Sep 23, alincoln wrote:

> Hey bro do you want to go ride horses tomorrow?
EOEMAIL;

    $parser = new PhabricatorMetaMTAEmailBodyParser();
    $stripped = $parser->stripTextBody($body);
    $this->assertEqual('On which horse shall you ride?', $stripped);
  }

  private function getEmailBodiesWithFullCommands() {
    $bodies = $this->getEmailBodies();
    $with_commands = array();
    foreach ($bodies as $body) {
      $with_commands[] = "!whatevs dude\n".$body;
    }
    return $with_commands;
  }

  private function getEmailBodiesWithPartialCommands() {
    $bodies = $this->getEmailBodies();
    $with_commands = array();
    foreach ($bodies as $body) {
      $with_commands[] = "!whatevs\n".$body;
    }
    return $with_commands;
  }

  private function getEmailBodiesWithMultipleCommands() {
    $bodies = $this->getEmailBodies();
    $with_commands = array();
    foreach ($bodies as $body) {
      $with_commands[] = "!top1\n\n!top2\n\npreface\n\n".$body;
    }
    return $with_commands;
  }

  private function getEmailBodiesWithSplitCommands() {
    $with_split = array();
    $with_split[] = "!cmd1\n!cmd2\nOKAY";
    $with_split[] = "!cmd1\nOKAY\n!cmd2";
    $with_split[] = "OKAY\n!cmd1\n!cmd2";
    return $with_split;
  }

  private function getEmailBodiesWithMiddleCommands() {
    $with_middle = array();
    $with_middle[] = "!cmd1\nHEAD\n!cmd2\nTAIL\n!cmd3";
    $with_middle[] = "!cmd1\nHEAD\n!cmd2\nTAIL";
    $with_middle[] = "HEAD\n!cmd2\nTAIL\n!cmd3";
    return $with_middle;
  }

  private function getEmailBodies() {
    $trailing_space = ' ';
    $emdash = "\xE2\x80\x94";

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
,
<<<EOMAIL
OKAY

> -----Original Message-----
>
> ...
EOMAIL
,
<<<EOMAIL
OKAY {$emdash}{$trailing_space}
Sent from Mailbox
EOMAIL
,
<<<EOMAIL
OKAY

{$emdash}
Sent from Mailbox
EOMAIL
,
<<<EOMAIL
OKAY

2015-05-06 11:21 GMT-07:00 Someone <someone@somewhere.com>:
> ...
EOMAIL
    );
  }

}
