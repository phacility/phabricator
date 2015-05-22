<?php

/**
 * Watches for "where is <symbol>?"
 */
final class PhabricatorBotSymbolHandler extends PhabricatorBotHandler {

  public function receiveMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
        $text = $message->getBody();

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
          $response = pht(
            "Multiple symbols named '%s': %s",
            $symbol,
            $default_uri);
        } else if (count($results) == 1) {
          $result = head($results);
          $response =
            $result['type'].' '.
            $result['name'].' '.
            '('.$result['language'].'): '.
            nonempty($result['uri'], $default_uri);
        } else {
          $response = pht("No symbol '%s' found anywhere.", $symbol);
        }

        $this->replyTo($message, $response);

        break;
    }
  }

}
