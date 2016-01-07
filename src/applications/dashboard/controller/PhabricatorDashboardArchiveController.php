<?php

final class PhabricatorDashboardArchiveController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('manage/'.$dashboard->getID().'/');

    if ($request->isFormPost()) {
      if ($dashboard->isArchived()) {
        $new_status = PhabricatorDashboard::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorDashboard::STATUS_ARCHIVED;
      }

      $xactions = array();

      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType(PhabricatorDashboardTransaction::TYPE_STATUS)
        ->setNewValue($new_status);

      id(new PhabricatorDashboardTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($dashboard, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($dashboard->isArchived()) {
      $title = pht('Activate Dashboard');
      $body = pht('This dashboard will become active again.');
      $button = pht('Activate Dashboard');
    } else {
      $title = pht('Archive Dashboard');
      $body = pht('This dashboard will be marked as archived.');
      $button = pht('Archive Dashboard');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);
  }

}
