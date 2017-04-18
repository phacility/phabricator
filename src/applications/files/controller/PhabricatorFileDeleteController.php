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
      // Mark the file for deletion, save it, and schedule a worker to
      // sweep by later and pick it up.
      $file->setIsDeleted(true)->save();

      PhabricatorWorker::scheduleTask(
        'FileDeletionWorker',
        array('objectPHID' => $file->getPHID()),
        array('priority' => PhabricatorWorker::PRIORITY_BULK));

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
