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
      // This is a builtin property, so we're going to reset it to the
      // default value.
      $field_list = PhabricatorCustomField::getObjectFields(
        $object,
        PhabricatorCustomField::ROLE_DEFAULT);

      // Note that we're NOT loading field values from the object: we just want
      // to get the field's default value so we can reset it.

      $fields = $field_list->getFields();
      $field = $fields[$key];

      $is_delete = false;
      $new_value = $field->getValueForStorage();

      // Now, load the field to get the old value.

      $field_list
        ->setViewer($viewer)
        ->readFieldsFromStorage($object);

      $old_value = $field->getValueForStorage();

      $title = pht('Reset Property');
      $body = pht('Reset this property to its default value?');
      $submit_text = pht('Reset');
    } else {
      // This is a custom property, so we're going to delete it outright.
      $is_delete = true;
      $old_value = $object->getAlmanacPropertyValue($key);
      $new_value = null;

      $title = pht('Delete Property');
      $body = pht('Delete this property? TODO: DOES NOT WORK YET');
      $submit_text = pht('Delete');
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      $xaction = $object->getApplicationTransactionTemplate()
        ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
        ->setMetadataValue('customfield:key', $key)
        ->setOldValue($old_value)
        ->setNewValue($new_value);

      // TODO: We aren't really deleting properties that we claim to delete
      // yet, but that needs to be specialized a little bit.

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
