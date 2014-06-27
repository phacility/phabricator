<?php

final class PhabricatorConfigIssueListController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('issue/');

    $issues = PhabricatorSetupCheck::runAllChecks();
    PhabricatorSetupCheck::setOpenSetupIssueCount(
      PhabricatorSetupCheck::countUnignoredIssues($issues));

    $list = $this->buildIssueList($issues);
    $list->setNoDataString(pht('There are no open setup issues.'));
    $list->setStackable(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Open Phabricator Setup Issues'))
      ->appendChild($list);

    $nav->appendChild(
      array(
        $box,
      ));

    $title = pht('Setup Issues');

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addTextCrumb(pht('Setup'), $this->getApplicationURI('issue/'));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function buildIssueList(array $issues) {
    assert_instances_of($issues, 'PhabricatorSetupIssue');
    $list = new PHUIObjectItemListView();
    $ignored_items = array();

    foreach ($issues as $issue) {
        $href = $this->getApplicationURI('/issue/'.$issue->getIssueKey().'/');
        $item = id(new PHUIObjectItemView())
          ->setHeader($issue->getName())
          ->setHref($href)
          ->addAttribute($issue->getSummary());
      if (!$issue->getIsIgnored()) {
        $item->setBarColor('yellow');
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-eye-slash')
            ->setWorkflow(true)
            ->setName(pht('Ignore'))
            ->setHref('/config/ignore/'.$issue->getIssueKey().'/'));
        $list->addItem($item);
      } else {
        $item->addIcon('none', pht('Ignored'));
        $item->setDisabled(true);
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-eye')
            ->setWorkflow(true)
            ->setName(pht('Unignore'))
            ->setHref('/config/unignore/'.$issue->getIssueKey().'/'));
        $item->setBarColor('none');
        $ignored_items[] = $item;
      }
    }

    foreach ($ignored_items as $item) {
      $list->addItem($item);
    }

    return $list;
  }

}
