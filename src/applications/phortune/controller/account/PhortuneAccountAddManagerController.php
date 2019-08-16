<?php

final class PhortuneAccountAddManagerController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $account = $this->getAccount();

    $id = $account->getID();

    $v_managers = array();
    $e_managers = null;
    $account_uri = $this->getApplicationURI("/account/{$id}/managers/");

    if ($request->isFormPost()) {
      $xactions = array();
      $v_managers = $request->getArr('managerPHIDs');
      $type_edge = PhabricatorTransactions::TYPE_EDGE;

      $xactions[] = id(new PhortuneAccountTransaction())
        ->setTransactionType($type_edge)
        ->setMetadataValue(
          'edge:type',
          PhortuneAccountHasMemberEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '+' => array_fuse($v_managers),
          ));

      $editor = id(new PhortuneAccountEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($account, $xactions);

        return id(new AphrontRedirectResponse())->setURI($account_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_managers = $ex->getShortMessage($type_edge);
      }
    }

    $account_phid = $account->getPHID();
    $handles = $viewer->loadHandles(array($account_phid));
    $handle = $handles[$account_phid];

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendInstructions(
        pht(
          'Choose one or more users to add as account managers. Managers '.
          'have full control of the account.'))
      ->appendControl(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Payment Account'))
          ->setValue($handle->renderLink()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('Add Managers'))
          ->setName('managerPHIDs')
          ->setValue($v_managers)
          ->setError($e_managers));

    return $this->newDialog()
      ->setTitle(pht('Add New Managers'))
      ->appendForm($form)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($account_uri)
      ->addSubmitButton(pht('Add Managers'));
  }

}
