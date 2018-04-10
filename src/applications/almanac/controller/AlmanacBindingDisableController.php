<?php

final class AlmanacBindingDisableController
  extends AlmanacServiceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $binding = id(new AlmanacBindingQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$binding) {
      return new Aphront404Response();
    }

    $id = $binding->getID();
    $is_disable = !$binding->getIsDisabled();
    $done_uri = $binding->getURI();

    if ($is_disable) {
      $disable_title = pht('Disable Binding');
      $disable_body = pht('Disable this binding?');
      $disable_button = pht('Disable Binding');

      $v_disable = 1;
    } else {
      $disable_title = pht('Enable Binding');
      $disable_body = pht('Enable this binding?');
      $disable_button = pht('Enable Binding');

      $v_disable = 0;
    }


    if ($request->isFormPost()) {
      $type_disable = AlmanacBindingDisableTransaction::TRANSACTIONTYPE;

      $xactions = array();

      $xactions[] = id(new AlmanacBindingTransaction())
        ->setTransactionType($type_disable)
        ->setNewValue($v_disable);

      $editor = id(new AlmanacBindingEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($binding, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    return $this->newDialog()
      ->setTitle($disable_title)
      ->appendParagraph($disable_body)
      ->addSubmitButton($disable_button)
      ->addCancelButton($done_uri);
  }

}
