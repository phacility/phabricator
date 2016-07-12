<?php

final class PhabricatorConfigIssueListController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('issue/');

    $issues = PhabricatorSetupCheck::runAllChecks();
    PhabricatorSetupCheck::setOpenSetupIssueKeys(
      PhabricatorSetupCheck::getUnignoredIssueKeys($issues),
      $update_database = true);

    $important = $this->buildIssueList(
      $issues, PhabricatorSetupCheck::GROUP_IMPORTANT);
    $php = $this->buildIssueList(
      $issues, PhabricatorSetupCheck::GROUP_PHP);
    $mysql = $this->buildIssueList(
      $issues, PhabricatorSetupCheck::GROUP_MYSQL);
    $other = $this->buildIssueList(
      $issues, PhabricatorSetupCheck::GROUP_OTHER);

    $setup_issues = array();
    if ($important) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Important Setup Issues'))
        ->setColor(PHUIObjectBoxView::COLOR_RED)
        ->setObjectList($important);
    }

    if ($php) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('PHP Setup Issues'))
        ->setObjectList($php);
    }

    if ($mysql) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('MySQL Setup Issues'))
        ->setObjectList($mysql);
    }

    if ($other) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Other Setup Issues'))
        ->setObjectList($other);
    }

    if (empty($setup_issues)) {
      $setup_issues[] = id(new PHUIInfoView())
        ->setTitle(pht('No Issues'))
        ->appendChild(
          pht('Your install has no current setup issues to resolve.'))
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
    }

    $title = pht('Setup Issues');

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addTextCrumb(pht('Setup'), $this->getApplicationURI('issue/'));

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn(array(
        $setup_issues,
    ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildIssueList(array $issues, $group) {
    assert_instances_of($issues, 'PhabricatorSetupIssue');
    $list = new PHUIObjectItemListView();
    $ignored_items = array();
    $items = 0;

    foreach ($issues as $issue) {
      if ($issue->getGroup() == $group) {
        $items++;
        $href = $this->getApplicationURI('/issue/'.$issue->getIssueKey().'/');
        $item = id(new PHUIObjectItemView())
          ->setHeader($issue->getName())
          ->setHref($href)
          ->addAttribute($issue->getSummary());
        if (!$issue->getIsIgnored()) {
          $item->setStatusIcon('fa-warning yellow');
          $list->addItem($item);
        } else {
          $item->addIcon('fa-eye-slash', pht('Ignored'));
          $item->setDisabled(true);
          $item->setStatusIcon('fa-warning grey');
          $ignored_items[] = $item;
        }
      }
    }

    foreach ($ignored_items as $item) {
      $list->addItem($item);
    }

    if ($items == 0) {
      return null;
    } else {
      return $list;
    }
  }

}
