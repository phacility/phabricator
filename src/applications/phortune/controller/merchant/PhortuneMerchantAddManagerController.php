<?php

final class PhortuneMerchantAddManagerController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfileImage(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $v_members = array();
    $e_members = null;
    $merchant_uri = $this->getApplicationURI("/merchant/manager/{$id}/");

    if ($request->isFormPost()) {
      $xactions = array();
      $v_members = $request->getArr('memberPHIDs');
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
          ->setLabel(pht('Members'))
          ->setName('memberPHIDs')
          ->setValue($v_members)
          ->setError($e_members));

    return $this->newDialog()
      ->setTitle(pht('Add New Manager'))
      ->appendForm($form)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($merchant_uri)
      ->addSubmitButton(pht('Add Manager'));

  }

}
