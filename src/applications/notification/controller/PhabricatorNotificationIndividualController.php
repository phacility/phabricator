<?php

final class PhabricatorNotificationIndividualController
  extends PhabricatorNotificationController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $stories = id(new PhabricatorNotificationQuery())
      ->setViewer($user)
      ->setUserPHID($user->getPHID())
      ->withKeys(array($request->getStr('key')))
      ->execute();

    if (!$stories) {
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'pertinent' => false,
        ));
    }

    $builder = new PhabricatorNotificationBuilder($stories);
    $content = $builder->buildView()->render();

    $response = array(
      'pertinent'         => true,
      'primaryObjectPHID' => head($stories)->getPrimaryObjectPHID(),
      'content'           => $content,
    );

    return id(new AphrontAjaxResponse())->setContent($response);
  }
}
