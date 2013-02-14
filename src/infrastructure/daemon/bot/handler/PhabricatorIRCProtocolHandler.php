<?php

/**
 * @deprecated
 */
final class PhabricatorIRCProtocolHandler extends PhabricatorBotHandler {

  public function receiveMessage(PhabricatorBotMessage $message) {
    static $warned;
    if (!$warned) {
      $warned = true;
      phlog("The PhabricatorIRCProtocolHandler has been deprecated.");
    }
  }

}
