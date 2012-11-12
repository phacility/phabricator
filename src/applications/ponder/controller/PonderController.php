<?php

abstract class PonderController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();
    $page->setApplicationName('Ponder!');
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

    if ($question && $question->getID()) {
      $side_nav->addFilter(
        null,
        'Q'.$question->getID(),
        'Q'.$question->getID());
      $side_nav->addSpacer();
    }

    $side_nav->addLabel('Create');
    $side_nav->addFilter('question/ask', 'Ask a Question');

    $side_nav->addSpacer();

    $side_nav->addLabel('Questions');
    $side_nav->addFilter('feed', 'All Questions');

    $side_nav->addSpacer();

    $side_nav->addLabel('User');
    $side_nav->addFilter('questions', 'Your Questions');
    $side_nav->addFilter('answers', 'Your Answers');

    return $side_nav;
  }

}
