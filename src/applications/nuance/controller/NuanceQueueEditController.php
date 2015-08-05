<?php

final class NuanceQueueEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $queues_uri = $this->getApplicationURI('queue/');

    $queue_id = $request->getURIData('id');
    $is_new = !$queue_id;
    if ($is_new) {
      $queue = NuanceQueue::initializeNewQueue();
      $cancel_uri = $queues_uri;
    } else {
      $queue = id(new NuanceQueueQuery())
        ->setViewer($viewer)
        ->withIDs(array($queue_id))
        ->executeOne();
      if (!$queue) {
        return new Aphront404Response();
      }
      $cancel_uri = $queue->getURI();
    }

    $v_name = $queue->getName();
    $e_name = true;
    $v_edit = $queue->getEditPolicy();
    $v_view = $queue->getViewPolicy();

    $validation_exception = null;
    if ($request->isFormPost()) {
      $e_name = null;

      $v_name = $request->getStr('name');
      $v_edit = $request->getStr('editPolicy');
      $v_view = $request->getStr('viewPolicy');

      $type_name = NuanceQueueTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new NuanceQueueTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new NuanceQueueTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new NuanceQueueTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $editor = id(new NuanceQueueEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {

        $editor->applyTransactions($queue, $xactions);

        $uri = $queue->getURI();
        return id(new AphrontRedirectResponse())->setURI($uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage($type_name);
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Queues'), $queues_uri);

    if ($is_new) {
      $title = pht('Create Queue');
      $crumbs->addTextCrumb(pht('Create'));
    } else {
      $title = pht('Edit %s', $queue->getName());
      $crumbs->addTextCrumb($queue->getName(), $queue->getURI());
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($queue)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($v_name))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($queue)
          ->setPolicies($policies)
          ->setValue($v_view)
          ->setName('viewPolicy'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicyObject($queue)
          ->setPolicies($policies)
          ->setValue($v_edit)
          ->setName('editPolicy'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue(pht('Save')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
