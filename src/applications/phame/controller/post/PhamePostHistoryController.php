<?php

final class PhamePostHistoryController extends PhamePostController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();

    if (!$post) {
      return new Aphront404Response();
    }

    $blog = $post->getBlog();

    $crumbs = $this->buildApplicationCrumbs();
    if ($blog) {
      $crumbs->addTextCrumb(
        $blog->getName(),
        $this->getApplicationURI('blog/view/'.$blog->getID().'/'));
    } else {
      $crumbs->addTextCrumb(
        pht('[No Blog]'),
        null);
    }
    $crumbs->addTextCrumb(
      $post->getTitle(),
      $this->getApplicationURI('post/view/'.$post->getID().'/'));
    $crumbs->addTextCrumb(pht('Post History'));
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $post,
      new PhamePostTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->newPage()
      ->setTitle($post->getTitle())
      ->setPageObjectPHIDs(array($post->getPHID()))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $timeline,
      ));
  }


}
