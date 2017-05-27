<?php

final class PhabricatorProjectDefaultController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $project_id = $request->getURIData('projectID');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($project_id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $target = $request->getURIData('target');
    switch ($target) {
      case 'filter':
        $title = pht('Set Board Default Filter');
        $body = pht(
          'Make the current filter the new default filter for this board? '.
          'All users will see the new filter as the default when they view '.
          'the board.');
        $button = pht('Save Default Filter');

        $xaction_value = $request->getStr('filter');
        $xaction_type = PhabricatorProjectFilterTransaction::TRANSACTIONTYPE;
        break;
      case 'sort':
        $title = pht('Set Board Default Order');
        $body = pht(
          'Make the current sort order the new default order for this board? '.
          'All users will see the new order as the default when they view '.
          'the board.');
        $button = pht('Save Default Order');

        $xaction_value = $request->getStr('order');
        $xaction_type = PhabricatorProjectSortTransaction::TRANSACTIONTYPE;
        break;
      default:
        return new Aphront404Response();
    }

    $id = $project->getID();

    $view_uri = $this->getApplicationURI("board/{$id}/");
    $view_uri = new PhutilURI($view_uri);
    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $view_uri->setQueryParam($key, $value);
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType($xaction_type)
        ->setNewValue($xaction_value);

      id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->setDisableWorkflowOnCancel(true)
      ->addCancelButton($view_uri)
      ->addSubmitButton($title);

    foreach ($request->getPassthroughRequestData() as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    return $dialog;
  }
}
