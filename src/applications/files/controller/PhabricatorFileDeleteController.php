<?php

final class PhabricatorFileDeleteController extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
      $file->delete();
      return id(new AphrontRedirectResponse())->setURI('/file/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($viewer);
    $dialog->setTitle(pht('Really delete file?'));
    $dialog->appendChild(hsprintf(
      '<p>%s</p>',
      pht(
        "Permanently delete '%s'? This action can not be undone.",
        $file->getName())));
    $dialog->addSubmitButton(pht('Delete'));
    $dialog->addCancelButton($file->getInfoURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
