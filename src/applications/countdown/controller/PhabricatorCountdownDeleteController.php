<?php

final class PhabricatorCountdownDeleteController
  extends PhabricatorCountdownController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $countdown = id(new PhabricatorCountdownQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
      ->executeOne();

    if (!$countdown) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $countdown->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/countdown/');
    }

    $inst = pht(
      'Are you sure you want to delete the countdown %s?',
      $countdown->getTitle());

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle(pht('Really delete this countdown?'));
    $dialog->appendChild(phutil_tag('p', array(), $inst));
    $dialog->addSubmitButton(pht('Delete'));
    $dialog->addCancelButton('/countdown/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
