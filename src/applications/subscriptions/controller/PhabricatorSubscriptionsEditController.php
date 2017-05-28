<?php

final class PhabricatorSubscriptionsEditController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $phid = $request->getURIData('phid');
    $action = $request->getURIData('action');

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    switch ($action) {
      case 'add':
        $is_add = true;
        break;
      case 'delete':
        $is_add = false;
        break;
      default:
        return new Aphront400Response();
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return $this->buildErrorResponse(
        pht('Bad Object'),
        pht('This object is not subscribable.'),
        $handle->getURI());
    }

    if ($object->isAutomaticallySubscribed($viewer->getPHID())) {
      return $this->buildErrorResponse(
        pht('Automatically Subscribed'),
        pht('You are automatically subscribed to this object.'),
        $handle->getURI());
    }

    if (!PhabricatorPolicyFilter::canInteract($viewer, $object)) {
      $lock = PhabricatorEditEngineLock::newForObject($viewer, $object);

      $dialog = $this->newDialog()
        ->addCancelButton($handle->getURI());

      return $lock->willBlockUserInteractionWithDialog($dialog);
    }

    if ($object instanceof PhabricatorApplicationTransactionInterface) {
      if ($is_add) {
        $xaction_value = array(
          '+' => array($viewer->getPHID()),
        );
      } else {
        $xaction_value = array(
          '-' => array($viewer->getPHID()),
        );
      }

      $xaction = id($object->getApplicationTransactionTemplate())
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue($xaction_value);

      $editor = id($object->getApplicationTransactionEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions(
        $object->getApplicationTransactionObject(),
        array($xaction));
    } else {

      // TODO: Eventually, get rid of this once everything implements
      // PhabriatorApplicationTransactionInterface.

      $editor = id(new PhabricatorSubscriptionsEditor())
        ->setActor($viewer)
        ->setObject($object);

      if ($is_add) {
        $editor->subscribeExplicit(array($viewer->getPHID()), $explicit = true);
      } else {
        $editor->unsubscribe(array($viewer->getPHID()));
      }

      $editor->save();
    }

    // TODO: We should just render the "Unsubscribe" action and swap it out
    // in the document for Ajax requests.
    return id(new AphrontReloadResponse())->setURI($handle->getURI());
  }

  private function buildErrorResponse($title, $message, $uri) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($message)
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
