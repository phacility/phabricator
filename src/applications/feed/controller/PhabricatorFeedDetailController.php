<?php

final class PhabricatorFeedDetailController extends PhabricatorFeedController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $story = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->withChronologicalKeys(array($id))
      ->executeOne();
    if (!$story) {
      return new Aphront404Response();
    }

    if ($request->getStr('text')) {
      $text = $story->renderText();
      return id(new AphrontPlainTextResponse())->setContent($text);
    }

    $feed = array($story);
    $builder = new PhabricatorFeedBuilder($feed);
    $builder->setUser($viewer);
    $feed_view = $builder->buildView();

    $title = pht('Story');

    $feed_view = phutil_tag_div('phabricator-feed-frame', $feed_view);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $feed_view,
      ),
      array(
        'title' => $title,
      ));
  }

}
