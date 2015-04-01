<?php

final class DifferentialRevisionMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorDifferentialApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'D[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'D');

    return id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needReviewerStatus(true)
      ->needReviewerAuthority(true)
      ->needActiveDiffs(true)
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new DifferentialReplyHandler();
  }

}
