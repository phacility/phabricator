<?php

final class PhabricatorProjectCoverController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $request->validateCSRF();

    $board_phid = $request->getStr('boardPHID');
    $object_phid = $request->getStr('objectPHID');
    $file_phid = $request->getStr('filePHID');

    $object = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_COVER_IMAGE)
      ->setNewValue($file->getPHID());

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($object, $xactions);

    return $this->newCardResponse($board_phid, $object_phid);
  }

}
