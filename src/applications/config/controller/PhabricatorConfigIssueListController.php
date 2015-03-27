<?php

final class PhabricatorConfigIssueListController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('issue/');

    $issues = PhabricatorSetupCheck::runAllChecks();
    PhabricatorSetupCheck::setOpenSetupIssueKeys(
      PhabricatorSetupCheck::getUnignoredIssueKeys($issues));

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
        ->appendChild($important);
    }

    if ($php) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('PHP Setup Issues'))
        ->appendChild($php);
    }

    if ($mysql) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('MySQL Setup Issues'))
        ->appendChild($mysql);
    }

    if ($other) {
      $setup_issues[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Other Setup Issues'))
        ->appendChild($other);
    }

    if (empty($setup_issues)) {
      $setup_issues[] = id(new PHUIInfoView())
        ->setTitle(pht('No Issues'))
        ->appendChild(
          pht('Your install has no current setup issues to resolve.'))
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
    }

    $nav->appendChild($setup_issues);

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

  private function buildIssueList(array $issues, $group) {
    assert_instances_of($issues, 'PhabricatorSetupIssue');
    $list = new PHUIObjectItemListView();
    $list->setStackable(true);
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
          $item->setBarColor('yellow');
          $list->addItem($item);
        } else {
          $item->addIcon('fa-eye-slash', pht('Ignored'));
          $item->setDisabled(true);
          $item->setBarColor('none');
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
