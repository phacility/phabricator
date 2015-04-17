<?php

final class PhabricatorConfigHistoryController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $xactions = id(new PhabricatorConfigTransactionQuery())
      ->setViewer($user)
      ->needComments(true)
      ->execute();

    $object = new PhabricatorConfigEntry();

    $xaction = $object->getApplicationTransactionTemplate();

    $view = $xaction->getApplicationTransactionViewObject();

    $timeline = $view
      ->setUser($user)
      ->setTransactions($xactions)
      ->setRenderAsFeed(true)
      ->setObjectPHID(PhabricatorPHIDConstants::PHID_VOID);

    $timeline->setShouldTerminate(true);

    $object->willRenderTimeline($timeline, $this->getRequest());

    $title = pht('Settings History');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb('Config', $this->getApplicationURI());
    $crumbs->addTextCrumb($title, '/config/history/');

    $nav = $this->buildSideNavView();
    $nav->selectFilter('history/');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($timeline);

    return $this->buildApplicationPage(
      array(
        $nav,
      ),
      array(
        'title' => $title,
      ));
  }

}
