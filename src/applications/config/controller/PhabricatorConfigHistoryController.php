<?php

final class PhabricatorConfigHistoryController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $xactions = id(new PhabricatorConfigTransactionQuery())
      ->setViewer($viewer)
      ->needComments(true)
      ->execute();

    $object = new PhabricatorConfigEntry();

    $xaction = $object->getApplicationTransactionTemplate();

    $view = $xaction->getApplicationTransactionViewObject();

    $timeline = $view
      ->setUser($viewer)
      ->setTransactions($xactions)
      ->setRenderAsFeed(true)
      ->setObjectPHID(PhabricatorPHIDConstants::PHID_VOID);

    $timeline->setShouldTerminate(true);

    $object->willRenderTimeline($timeline, $this->getRequest());

    $title = pht('Settings History');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb('Config', $this->getApplicationURI());
    $crumbs->addTextCrumb($title, '/config/history/');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('history/');

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn(array(
        $timeline,
    ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
