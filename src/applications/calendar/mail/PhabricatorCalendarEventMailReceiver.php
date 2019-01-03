<?php

final class PhabricatorCalendarEventMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorCalendarApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'E[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 1);

    return id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new PhabricatorCalendarReplyHandler();
  }

}
