<?php

final class HarbormasterBuildCancelController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $build = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if ($build === null) {
      return new Aphront404Response();
    }

    $build_uri = $this->getApplicationURI('/build/'.$build->getID());

    if ($request->isDialogFormPost()) {
      $build->setCancelRequested(1);
      $build->save();

      return id(new AphrontRedirectResponse())->setURI($build_uri);
    }

    $dialog = new AphrontDialogView();
    $dialog->setTitle(pht('Really cancel build?'))
      ->setUser($viewer)
      ->addSubmitButton(pht('Cancel'))
      ->addCancelButton($build_uri, pht('Don\'t Cancel'));
    $dialog->appendChild(
      phutil_tag(
        'p',
        array(),
        pht(
          'Really cancel this build?')));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
