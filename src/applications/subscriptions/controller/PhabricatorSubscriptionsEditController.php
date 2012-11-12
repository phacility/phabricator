<?php

final class PhabricatorSubscriptionsEditController
  extends PhabricatorController {

  private $phid;
  private $action;

  public function willProcessRequest(array $data) {
    $this->phid = idx($data, 'phid');
    $this->action = idx($data, 'action');
  }

  public function processRequest() {
    $request = $this->getRequest();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    switch ($this->action) {
      case 'add':
        $is_add = true;
        break;
      case 'delete':
        $is_add = false;
        break;
      default:
        return new Aphront400Response();
    }

    $user = $request->getUser();
    $phid = $this->phid;

    // TODO: This is a policy test because `loadObjects()` is not currently
    // policy-aware. Once it is, we can collapse this.
    $handle = PhabricatorObjectHandleData::loadOneHandle($phid, $user);
    if (!$handle->isComplete()) {
      return new Aphront404Response();
    }

    $objects = id(new PhabricatorObjectHandleData(array($phid)))
      ->loadObjects();
    $object = idx($objects, $phid);

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return $this->buildErrorResponse(
        pht('Bad Object'),
        pht('This object is not subscribable.'),
        $handle->getURI());
    }

    if ($object->isAutomaticallySubscribed($user->getPHID())) {
      return $this->buildErrorResponse(
        pht('Automatically Subscribed'),
        pht('You are automatically subscribed to this object.'),
        $handle->getURI());
    }

    $editor = id(new PhabricatorSubscriptionsEditor())
      ->setActor($user)
      ->setObject($object);

    if ($is_add) {
      $editor->subscribeExplicit(array($user->getPHID()), $explicit = true);
    } else {
      $editor->unsubscribe(array($user->getPHID()));
    }

    $editor->save();

    // TODO: We should just render the "Unsubscribe" action and swap it out
    // in the document for Ajax requests.
    return id(new AphrontReloadResponse())->setURI($handle->getURI());
  }

  private function buildErrorResponse($title, $message, $uri) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle($title)
      ->appendChild($message)
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
