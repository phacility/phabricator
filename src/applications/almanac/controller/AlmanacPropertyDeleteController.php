<?php

final class AlmanacPropertyDeleteController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getStr('objectPHID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    if (!($object instanceof AlmanacPropertyInterface)) {
      return new Aphront404Response();
    }

    $key = $request->getStr('key');
    if (!strlen($key)) {
      return new Aphront404Response();
    }

    $cancel_uri = $object->getURI();

    $builtins = $object->getAlmanacPropertyFieldSpecifications();
    $is_builtin = isset($builtins[$key]);

    if ($is_builtin) {
      $title = pht('Reset Property');
      $body = pht(
        'Reset property "%s" to its default value?',
        $key);
      $submit_text = pht('Reset Property');
    } else {
      $title = pht('Delete Property');
      $body = pht(
        'Delete property "%s"?',
        $key);
      $submit_text = pht('Delete Property');
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      $xaction = $object->getApplicationTransactionTemplate()
        ->setTransactionType(AlmanacTransaction::TYPE_PROPERTY_REMOVE)
        ->setMetadataValue('almanac.property', $key);

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($object, array($xaction));
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->addHiddenInput('objectPHID', $object->getPHID())
      ->addHiddenInput('key', $key)
      ->appendParagraph($body)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton($submit_text);
  }

}
