<?php

/**
 * Watches for "where is <symbol>?"
 *
 * @group irc
 */
final class PhabricatorIRCSymbolHandler extends PhabricatorIRCHandler {

  public function receiveMessage(PhabricatorIRCMessage $message) {

    switch ($message->getCommand()) {
      case 'PRIVMSG':
        $reply_to = $message->getReplyTo();
        if (!$reply_to) {
          break;
        }

        $text = $message->getMessageText();

        $matches = null;
        if (!preg_match('/where(?: in the world)? is (\S+?)\?/i',
            $text, $matches)) {
          break;
        }

        $symbol = $matches[1];
        $results = $this->getConduit()->callMethodSynchronous(
          'diffusion.findsymbols',
          array(
            'name' => $symbol,
          ));

        $default_uri = $this->getURI('/diffusion/symbol/'.$symbol.'/');

        if (count($results) > 1) {
          $response = "Multiple symbols named '{$symbol}': {$default_uri}";
        } else if (count($results) == 1) {
          $result = head($results);
          $response =
            $result['type'].' '.
            $result['name'].' '.
            '('.$result['language'].'): '.
            nonempty($result['uri'], $default_uri);
        } else {
          $response = "No symbol '{$symbol}' found anywhere.";
        }

        $this->write('PRIVMSG', "{$reply_to} :{$response}");

        break;
    }
  }

}
