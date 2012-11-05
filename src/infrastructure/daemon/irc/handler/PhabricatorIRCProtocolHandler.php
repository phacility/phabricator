<?php

/**
 * Implements the base IRC protocol so servers don't kick you off.
 *
 * @group irc
 */
final class PhabricatorIRCProtocolHandler extends PhabricatorIRCHandler {

  public function receiveMessage(PhabricatorIRCMessage $message) {
    switch ($message->getCommand()) {
      case '422': // Error - no MOTD
      case '376': // End of MOTD
        $nickpass = $this->getConfig('nickpass');
        if ($nickpass) {
          $this->write('PRIVMSG', "nickserv :IDENTIFY {$nickpass}");
        }
        $join = $this->getConfig('join');
        if (!$join) {
          throw new Exception("Not configured to join any channels!");
        }
        foreach ($join as $channel) {
          $this->write('JOIN', $channel);
        }
        break;
      case 'PING':
        $this->write('PONG', $message->getRawData());
        break;
    }
  }

}
