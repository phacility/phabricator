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
    $list->setNoDataString(pht("There are no open setup issues."));

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Open Phabricator Setup Issues'));

    $nav->appendChild(
      array(
        $header,
        $list,
      ));

    $title = pht('Setup Issues');

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Setup'))
          ->setHref($this->getApplicationURI('issue/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildIssueList(array $issues) {
    assert_instances_of($issues, 'PhabricatorSetupIssue');
    $list = new PhabricatorObjectItemListView();
    $list->setStackable(true);
    $ignored_items = array();

    foreach ($issues as $issue) {
        $href = $this->getApplicationURI('/issue/'.$issue->getIssueKey().'/');
        $item = id(new PhabricatorObjectItemView())
          ->setHeader($issue->getName())
          ->setHref($href)
          ->setBarColor('yellow')
          ->addAttribute($issue->getSummary());
      if (!$issue->getIsIgnored()) {
        $item->addIcon('warning', pht('Setup Warning'));
        $link = javelin_tag(
                 'a',
                 array('href'  => '/config/ignore/'.$issue->getIssueKey().'/',
                       'sigil' => 'workflow'),
                 pht('Ignore'));
        $item->addAttribute($link);
        $list->addItem($item);
      } else {
        $item->addIcon('none', pht('Ignored'));
        $link = javelin_tag(
                 'a',
                 array('href'  => '/config/unignore/'.$issue->getIssueKey().'/',
                       'sigil' => 'workflow'),
                 pht('Unignore'));
        $item->addAttribute($link);
        $ignored_items[] = $item;
      }
    }

    foreach ($ignored_items as $item) {
      $list->addItem($item);
    }

    return $list;
  }

}
