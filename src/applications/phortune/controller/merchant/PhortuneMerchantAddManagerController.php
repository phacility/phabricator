<?php

final class PhortuneMerchantAddManagerController
  extends PhortuneMerchantController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $v_members = array();
    $e_members = null;
    $merchant_uri = $merchant->getManagersURI();

    if ($request->isFormOrHiSecPost()) {
      $xactions = array();
      $v_members = $request->getArr('managerPHIDs');
      $type_edge = PhabricatorTransactions::TYPE_EDGE;

      $xactions[] = id(new PhortuneMerchantTransaction())
        ->setTransactionType($type_edge)
        ->setMetadataValue(
          'edge:type',
          PhortuneMerchantHasMemberEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '+' => array_fuse($v_members),
          ));

      $editor = id(new PhortuneMerchantEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($merchant, $xactions);

        return id(new AphrontRedirectResponse())->setURI($merchant_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_members = $ex->getShortMessage($type_edge);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('New Managers'))
          ->setName('managerPHIDs')
          ->setValue($v_members)
          ->setError($e_members));

    return $this->newDialog()
      ->setTitle(pht('Add New Managers'))
      ->appendForm($form)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($merchant_uri)
      ->addSubmitButton(pht('Add Manager'));

  }

}
