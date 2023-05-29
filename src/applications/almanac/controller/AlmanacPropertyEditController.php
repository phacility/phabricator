<?php

final class AlmanacPropertyEditController
  extends AlmanacPropertyController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadPropertyObject();
    if ($response) {
      return $response;
    }

    $object = $this->getPropertyObject();

    $cancel_uri = $object->getURI();
    $property_key = $request->getStr('key');

    if (!phutil_nonempty_string($property_key)) {
      return $this->buildPropertyKeyResponse($cancel_uri, null);
    } else {
      $error = null;
      try {
        AlmanacNames::validateName($property_key);
      } catch (Exception $ex) {
        $error = $ex->getMessage();
      }

      // NOTE: If you enter an existing name, we're just treating it as an
      // edit operation. This might be a little confusing.

      if ($error !== null) {
        if ($request->isFormPost()) {
          // The user is creating a new property and picked a bad name. Give
          // them an opportunity to fix it.
          return $this->buildPropertyKeyResponse($cancel_uri, $error);
        } else {
          // The user is editing an invalid property.
          return $this->newDialog()
            ->setTitle(pht('Invalid Property'))
            ->appendParagraph(
              pht(
                'The property name "%s" is invalid. This property can not '.
                'be edited.',
                $property_key))
            ->appendParagraph($error)
            ->addCancelButton($cancel_uri);
        }
      }
    }

    return $object->newAlmanacPropertyEditEngine()
      ->addContextParameter('objectPHID')
      ->addContextParameter('key')
      ->setTargetObject($object)
      ->setPropertyKey($property_key)
      ->setController($this)
      ->buildResponse();
  }

  private function buildPropertyKeyResponse($cancel_uri, $error) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $v_key = $request->getStr('key');

    if ($error !== null) {
      $e_key = pht('Invalid');
    } else {
      $e_key = true;
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('key')
          ->setLabel(pht('Name'))
          ->setValue($v_key)
          ->setError($e_key));

    $errors = array();
    if ($error !== null) {
      $errors[] = $error;
    }

    return $this->newDialog()
      ->setTitle(pht('Add Property'))
      ->addHiddenInput('objectPHID', $request->getStr('objectPHID'))
      ->setErrors($errors)
      ->appendForm($form)
      ->addSubmitButton(pht('Continue'))
      ->addCancelButton($cancel_uri);
  }

}
