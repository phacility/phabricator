<?php

final class PonderVoteSaveController extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $phid = $request->getStr('phid');
    $newvote = $request->getInt('vote');

    if (1 < $newvote || $newvote < -1) {
      return new Aphront400Response();
    }

    $target = null;

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $editor = id(new PonderVoteEditor())
      ->setVotable($object)
      ->setActor($viewer)
      ->setVote($newvote)
      ->saveVote();

    return id(new AphrontAjaxResponse())->setContent(array());
  }
}
