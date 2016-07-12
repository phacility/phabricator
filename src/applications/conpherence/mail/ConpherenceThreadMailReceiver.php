<?php

final class ConpherenceThreadMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorConpherenceApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'Z[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'Z');

    return id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new ConpherenceReplyHandler();
  }

}
