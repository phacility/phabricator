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

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($phid))
      ->executeOne();

    if (phid_get_type($phid) == PhabricatorProjectProjectPHIDType::TYPECONST) {
      // TODO: This is a big hack, but a weak argument for adding some kind
      // of "load for role" feature to ObjectQuery, and also not a really great
      // argument for adding some kind of "load extra stuff" feature to
      // SubscriberInterface. Do this for now and wait for the best way forward
      // to become more clear?

      $object = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withPHIDs(array($phid))
        ->needWatchers(true)
        ->executeOne();
    } else {
      $object = id(new PhabricatorObjectQuery())
        ->setViewer($user)
        ->withPHIDs(array($phid))
        ->executeOne();
    }

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

    if (!$object->shouldAllowSubscription($user->getPHID())) {
      return $this->buildErrorResponse(
        pht('You Can Not Subscribe'),
        pht('You can not subscribe to this object.'),
        $handle->getURI());
    }

    if ($object instanceof PhabricatorApplicationTransactionInterface) {
      if ($is_add) {
        $xaction_value = array(
          '+' => array($user->getPHID()),
        );
      } else {
        $xaction_value = array(
          '-' => array($user->getPHID()),
        );
      }

      $xaction = id($object->getApplicationTransactionTemplate())
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue($xaction_value);

      $editor = id($object->getApplicationTransactionEditor())
        ->setActor($user)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions(
        $object->getApplicationTransactionObject(),
        array($xaction));
    } else {

      // TODO: Eventually, get rid of this once everything implements
      // PhabriatorApplicationTransactionInterface.

      $editor = id(new PhabricatorSubscriptionsEditor())
        ->setActor($user)
        ->setObject($object);

      if ($is_add) {
        $editor->subscribeExplicit(array($user->getPHID()), $explicit = true);
      } else {
        $editor->unsubscribe(array($user->getPHID()));
      }

      $editor->save();
    }

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
