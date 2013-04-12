<?php

abstract class PonderController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();
    $page->setApplicationName(pht('Ponder!'));
    $page->setBaseURI('/ponder/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x97\xB3");
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_QUESTIONS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function buildSideNavView(PonderQuestion $question = null) {
    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $side_nav->addLabel(pht('Questions'));
    $side_nav->addFilter('feed', pht('All Questions'));
    $side_nav->addFilter('questions', pht('Your Questions'));
    $side_nav->addFilter('answers', pht('Your Answers'));

    return $side_nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setName(pht('New Question'))
          ->setHref('question/ask')
          ->setIcon('create'));

    return $crumbs;
  }

}
