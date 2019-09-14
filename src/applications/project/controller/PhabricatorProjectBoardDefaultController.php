<?php

final class PhabricatorProjectBoardDefaultController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProjectForEdit();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $state = $this->getViewState();
    $board_uri = $state->newWorkboardURI();
    $remove_param = null;

    $target = $request->getURIData('target');
    switch ($target) {
      case 'filter':
        $title = pht('Set Board Default Filter');
        $body = pht(
          'Make the current filter the new default filter for this board? '.
          'All users will see the new filter as the default when they view '.
          'the board.');
        $button = pht('Save Default Filter');

        $xaction_value = $state->getQueryKey();
        $xaction_type = PhabricatorProjectFilterTransaction::TRANSACTIONTYPE;

        $remove_param = 'filter';
        break;
      case 'sort':
        $title = pht('Set Board Default Order');
        $body = pht(
          'Make the current sort order the new default order for this board? '.
          'All users will see the new order as the default when they view '.
          'the board.');
        $button = pht('Save Default Order');

        $xaction_value = $state->getOrder();
        $xaction_type = PhabricatorProjectSortTransaction::TRANSACTIONTYPE;

        $remove_param = 'order';
        break;
      default:
        return new Aphront404Response();
    }

    $id = $project->getID();

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

      // If the parameter we just modified is present in the query string,
      // throw it away so the user is redirected back to the default view of
      // the board, allowing them to see the new default behavior.
      $board_uri->removeQueryParam($remove_param);

      return id(new AphrontRedirectResponse())->setURI($board_uri);
    }

    return $this->newWorkboardDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($board_uri)
      ->addSubmitButton($title);
  }
}
