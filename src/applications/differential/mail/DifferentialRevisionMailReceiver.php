<?php

final class DifferentialRevisionMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorDifferentialApplication');
  }

  protected function getObjectPattern() {
    return 'D[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 1);

    return id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needReviewers(true)
      ->needReviewerAuthority(true)
      ->needActiveDiffs(true)
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new DifferentialReplyHandler();
  }

}
