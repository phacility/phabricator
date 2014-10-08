<?php

final class PhabricatorFeedDetailController extends PhabricatorFeedController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $story = id(new PhabricatorFeedQuery())
      ->setViewer($user)
      ->withChronologicalKeys(array($this->id))
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
    $builder->setUser($user);
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
