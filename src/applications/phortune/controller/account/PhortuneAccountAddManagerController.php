<?php

final class PhortuneAccountAddManagerController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $account = id(new PhortuneAccountQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$account) {
      return new Aphront404Response();
    }

    $v_managers = array();
    $e_managers = null;
    $account_uri = $this->getApplicationURI("/account/manager/{$id}/");

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

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('Managers'))
          ->setName('managerPHIDs')
          ->setValue($v_managers)
          ->setError($e_managers));

    return $this->newDialog()
      ->setTitle(pht('Add New Manager'))
      ->appendForm($form)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton($account_uri)
      ->addSubmitButton(pht('Add Manager'));

  }

}
