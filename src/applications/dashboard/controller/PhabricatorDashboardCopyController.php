<?php

final class PhabricatorDashboardCopyController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $manage_uri = $this->getApplicationURI('manage/'.$dashboard->getID().'/');

    if ($request->isFormPost()) {

      $copy = PhabricatorDashboard::initializeNewDashboard($viewer);
      $copy = PhabricatorDashboard::copyDashboard($copy, $dashboard);

      $copy->setName(pht('Copy of %s', $copy->getName()));

      // Set up all the edges for the new dashboard.

      $xactions = array();
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse($dashboard->getPanelPHIDs()),
          ));

      $editor = id(new PhabricatorDashboardTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($copy, $xactions);

      $manage_uri = $this->getApplicationURI('edit/'.$copy->getID().'/');
      return id(new AphrontRedirectResponse())->setURI($manage_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Copy Dashboard'))
      ->appendParagraph(
        pht(
          'Create a copy of the dashboard "%s"?',
          phutil_tag('strong', array(), $dashboard->getName())))
      ->addCancelButton($manage_uri)
      ->addSubmitButton(pht('Create Copy'));
  }

}
