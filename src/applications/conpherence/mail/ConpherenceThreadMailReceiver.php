<?php

final class ConpherenceThreadMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorConpherenceApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    // TODO: Only recognize "Z" once we get closer to shipping Calendar.
    return '[EZ][1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    // TODO: Only recognize "Z" once we get closer to shipping Calendar.
    $id = (int)trim($pattern, 'EZ');

    return id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new ConpherenceReplyHandler();
  }

}
