<?php

final class HeraldWebhookTestController
  extends HeraldWebhookController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $hook = id(new HeraldWebhookQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$hook) {
      return new Aphront404Response();
    }

    $v_object = null;
    $e_object = null;
    $errors = array();
    if ($request->isFormPost()) {

      $v_object = $request->getStr('object');
      if (!strlen($v_object)) {
        $object = $hook;
      } else {
        $objects = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withNames(array($v_object))
          ->execute();
        if ($objects) {
          $object = head($objects);
        } else {
          $e_object = pht('Invalid');
          $errors[] = pht('Specified object could not be loaded.');
        }
      }

      if (!$errors) {
        $xaction_query =
          PhabricatorApplicationTransactionQuery::newQueryForObject($object);

        $xactions = $xaction_query
          ->withObjectPHIDs(array($object->getPHID()))
          ->setViewer($viewer)
          ->setLimit(10)
          ->execute();

        $request = HeraldWebhookRequest::initializeNewWebhookRequest($hook)
          ->setObjectPHID($object->getPHID())
          ->setTriggerPHIDs(array($viewer->getPHID()))
          ->setIsTestAction(true)
          ->setTransactionPHIDs(mpull($xactions, 'getPHID'))
          ->save();

        $request->queueCall();

        $next_uri = $hook->getURI().'request/'.$request->getID().'/';

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }
    }

    $instructions = <<<EOREMARKUP
Optionally, choose an object to generate test data for (like `D123` or `T234`).

The 10 most recent transactions for the object will be submitted to the webhook.
EOREMARKUP;

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Object'))
          ->setName('object')
          ->setError($e_object)
          ->setValue($v_object));

    return $this->newDialog()
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('New Test Request'))
      ->appendParagraph(new PHUIRemarkupView($viewer, $instructions))
      ->appendForm($form)
      ->addCancelButton($hook->getURI())
      ->addSubmitButton(pht('Test Webhook'));
  }


}
