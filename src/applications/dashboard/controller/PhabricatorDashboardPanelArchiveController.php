<?php

final class PhabricatorDashboardPanelArchiveController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $next_uri = '/'.$panel->getMonogram();

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorDashboardPanelTransaction())
        ->setTransactionType(PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE)
        ->setNewValue((int)!$panel->getIsArchived());

      id(new PhabricatorDashboardPanelTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->applyTransactions($panel, $xactions);

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    if ($panel->getIsArchived()) {
      $title = pht('Activate Panel?');
      $body = pht(
        'This panel will be reactivated and appear in other interfaces as '.
        'an active panel.');
      $submit_text = pht('Activate Panel');
    } else {
      $title = pht('Archive Panel?');
      $body = pht(
        'This panel will be archived and no longer appear in lists of active '.
        'panels.');
      $submit_text = pht('Archive Panel');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($submit_text)
      ->addCancelButton($next_uri);
  }

}
