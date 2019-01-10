<?php

final class ManiphestTaskMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorManiphestApplication');
  }

  protected function getObjectPattern() {
    return 'T[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'T');

    return id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new ManiphestReplyHandler();
  }

}
