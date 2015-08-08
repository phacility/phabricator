<?php

final class PhabricatorNotificationIndividualController
  extends PhabricatorNotificationController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $stories = id(new PhabricatorNotificationQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withKeys(array($request->getStr('key')))
      ->execute();

    if (!$stories) {
      return $this->buildEmptyResponse();
    }

    $story = head($stories);
    if ($story->getAuthorPHID() === $viewer->getPHID()) {
      // Don't show the user individual notifications about their own
      // actions. Primarily, this stops pages from showing notifications
      // immediately after you click "Submit" on a comment form if the
      // notification server returns faster than the web server.

      // TODO: It would be nice to retain the "page updated" bubble on copies
      // of the page that are open in other tabs, but there isn't an obvious
      // way to do this easily.

      return $this->buildEmptyResponse();
    }

    $builder = new PhabricatorNotificationBuilder(array($story));
    $content = $builder->buildView()->render();
    $dict = $builder->buildDict();
    $data = $dict[0];

    $response = array(
      'pertinent'         => true,
      'primaryObjectPHID' => $story->getPrimaryObjectPHID(),
      'desktopReady'      => $data['desktopReady'],
      'href'              => $data['href'],
      'icon'              => $data['icon'],
      'title'             => $data['title'],
      'body'              => $data['body'],
      'content'           => hsprintf('%s', $content),
    );

    return id(new AphrontAjaxResponse())->setContent($response);
  }

  private function buildEmptyResponse() {
    return id(new AphrontAjaxResponse())->setContent(
      array(
        'pertinent' => false,
      ));
  }

}
