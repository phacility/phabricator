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
    $header = $this->buildHeaderView($title);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('history/');

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

}
