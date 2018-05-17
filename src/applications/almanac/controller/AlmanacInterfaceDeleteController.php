<?php

final class AlmanacInterfaceDeleteController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $interface = id(new AlmanacInterfaceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$interface) {
      return new Aphront404Response();
    }

    $device = $interface->getDevice();
    $device_uri = $device->getURI();

    if ($interface->loadIsInUse()) {
      return $this->newDialog()
        ->setTitle(pht('Interface In Use'))
        ->appendParagraph(
          pht(
            'You can not delete this interface because it is currently in '.
            'use. One or more services are bound to it.'))
        ->addCancelButton($device_uri);
    }

    if ($request->isFormPost()) {
      $type_destroy = AlmanacInterfaceDestroyTransaction::TRANSACTIONTYPE;

      $xactions = array();

      $xactions[] = $interface->getApplicationTransactionTemplate()
        ->setTransactionType($type_destroy)
        ->setNewValue(true);

      $editor = id(new AlmanacInterfaceEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($interface, $xactions);

      return id(new AphrontRedirectResponse())->setURI($device_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Delete Interface'))
      ->appendParagraph(
        pht(
          'Remove interface %s on device %s?',
          phutil_tag('strong', array(), $interface->renderDisplayAddress()),
          phutil_tag('strong', array(), $device->getName())))
      ->addCancelButton($device_uri)
      ->addSubmitButton(pht('Delete Interface'));
  }

}
