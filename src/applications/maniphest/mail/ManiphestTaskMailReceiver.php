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

    $results = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->execute();

    return head($results);
  }

  protected function getTransactionReplyHandler() {
    return new ManiphestReplyHandler();
  }

}
