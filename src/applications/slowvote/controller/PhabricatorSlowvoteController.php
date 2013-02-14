<?php

/**
 * @group slowvote
 */
abstract class PhabricatorSlowvoteController extends PhabricatorController {

  const VIEW_ALL      = 'all';
  const VIEW_CREATED  = 'created';
  const VIEW_VOTED    = 'voted';

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Slowvote'));
    $page->setBaseURI('/vote/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9C\x94");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildSideNavView($filter = null, $for_app = false) {

    $views = $this->getViews();
    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(new PhutilURI('/vote/view/'));
    foreach ($views as $key => $name) {
      $side_nav->addFilter($key, $name);
    }
    if ($filter) {
      $side_nav->selectFilter($filter, null);
    }

    if ($for_app) {
      $side_nav->addFilter('', pht('Create Question'),
        $this->getApplicationURI('create/'));
    }

    return $side_nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Create Question'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('create'));

    return $crumbs;
  }

  public function getViews() {
    return array(
      self::VIEW_ALL      => pht('All Slowvotes'),
      self::VIEW_CREATED  => pht('Created'),
      self::VIEW_VOTED    => pht('Voted In'),
    );
  }

}
