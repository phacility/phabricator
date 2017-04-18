<?php

final class PhabricatorFileDeleteController extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withIsDeleted(false)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    if (($viewer->getPHID() != $file->getAuthorPHID()) &&
        (!$viewer->getIsAdmin())) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorFileTransaction())
        ->setTransactionType(PhabricatorFileDeleteTransaction::TRANSACTIONTYPE)
        ->setNewValue(true);

      id(new PhabricatorFileEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($file, $xactions);

      return id(new AphrontRedirectResponse())->setURI('/file/');
    }

    return $this->newDialog()
      ->setTitle(pht('Really delete file?'))
      ->appendChild(hsprintf(
      '<p>%s</p>',
      pht(
        'Permanently delete "%s"? This action can not be undone.',
        $file->getName())))
        ->addSubmitButton(pht('Delete'))
        ->addCancelButton($file->getInfoURI());
  }
}
