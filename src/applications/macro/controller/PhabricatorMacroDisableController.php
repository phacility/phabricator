<?php

final class PhabricatorMacroDisableController
  extends PhabricatorMacroController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $this->requireApplicationCapability(
      PhabricatorMacroManageCapability::CAPABILITY);

    $macro = id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$macro) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('/view/'.$id.'/');

    if ($request->isDialogFormPost() || $macro->getIsDisabled()) {
      $xaction = id(new PhabricatorMacroTransaction())
        ->setTransactionType(PhabricatorMacroTransaction::TYPE_DISABLED)
        ->setNewValue($macro->getIsDisabled() ? 0 : 1);

      $editor = id(new PhabricatorMacroEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request);

      $xactions = $editor->applyTransactions($macro, array($xaction));

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle(pht('Really disable macro?'))
      ->appendChild(
        phutil_tag(
          'p',
          array(),
          pht(
            'Really disable the much-beloved image macro %s? '.
            'It will be sorely missed.',
          $macro->getName())))
      ->setSubmitURI($this->getApplicationURI('/disable/'.$id.'/'))
      ->addSubmitButton(pht('Disable'))
      ->addCancelButton($view_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
