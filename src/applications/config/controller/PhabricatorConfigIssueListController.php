<?php

final class PhabricatorConfigIssueListController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('issue/');

    $engine = new PhabricatorSetupEngine();
    $response = $engine->execute();
    if ($response) {
      return $response;
    }
    $issues = $engine->getIssues();

    $important = $this->buildIssueList(
      $issues,
      PhabricatorSetupCheck::GROUP_IMPORTANT,
      'fa-warning');
    $php = $this->buildIssueList(
      $issues,
      PhabricatorSetupCheck::GROUP_PHP,
      'fa-code');
    $mysql = $this->buildIssueList(
      $issues,
      PhabricatorSetupCheck::GROUP_MYSQL,
      'fa-database');
    $other = $this->buildIssueList(
      $issues,
      PhabricatorSetupCheck::GROUP_OTHER,
      'fa-question-circle');

    $no_issues = null;
    if (empty($issues)) {
      $no_issues = id(new PHUIInfoView())
        ->setTitle(pht('No Issues'))
        ->appendChild(
          pht('Your install has no current setup issues to resolve.'))
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
    }

    $title = pht('Setup Issues');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addTextCrumb(pht('Setup Issues'))
      ->setBorder(true);

    $page = array(
      $no_issues,
      $important,
      $php,
      $mysql,
      $other,
    );

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($page);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');
  }

  private function buildIssueList(array $issues, $group, $fonticon) {
    assert_instances_of($issues, 'PhabricatorSetupIssue');
    $list = new PHUIObjectItemListView();
    $list->setBig(true);
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
          $icon = id(new PHUIIconView())
            ->setIcon($fonticon)
            ->setBackground('bg-sky');
          $item->setImageIcon($icon);
          $list->addItem($item);
        } else {
          $icon = id(new PHUIIconView())
            ->setIcon('fa-eye-slash')
            ->setBackground('bg-grey');
          $item->setDisabled(true);
          $item->setImageIcon($icon);
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
