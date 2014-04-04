<?php

abstract class PhabricatorAuditController extends PhabricatorController {

  public $filter;

  public function buildSideNavView() {

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/audit/view/'));
    $nav->addLabel(pht('Active'));
    $nav->addFilter('active', pht('Need Attention'));

    $nav->addLabel(pht('Audits'));
    $nav->addFilter('audits', pht('All'));
    $nav->addFilter('user', pht('By User'));
    $nav->addFilter('project', pht('By Project'));
    $nav->addFilter('package', pht('By Package'));
    $nav->addFilter('repository', pht('By Repository'));

    $nav->addLabel(pht('Commits'));
    $nav->addFilter('commits', pht('All'));
    $nav->addFilter('author', pht('By Author'));
    $nav->addFilter('packagecommits', pht('By Package'));

    $this->filter = $nav->selectFilter($this->filter, 'active');

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Audit'));
    $page->setBaseURI('/audit/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9C\x8D");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
