<?php

final class PhabricatorRepositoryDeleteController
  extends PhabricatorRepositoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $repository = id(new PhabricatorRepository())->load($this->id);
    if (!$repository) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isDialogFormPost()) {
      return id(new AphrontRedirectResponse())->setURI('/repository/');
    }

    $dialog = new AphrontDialogView();
    $text_1 = pht('If you really want to delete the repository, you must run:');
    $command = 'bin/repository delete '.$repository->getCallsign();
    $text_2 = pht('Repositories touch many objects and as such deletes are '.
                  'prohibitively expensive to run from the web UI.');
    $body = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      array(
        phutil_tag('p', array(), $text_1),
        phutil_tag('p', array(),
          phutil_tag('tt', array(), $command)),
        phutil_tag('p', array(), $text_2),
      ));

    $dialog
      ->setUser($request->getUser())
      ->setTitle(pht('Really want to delete the repository?'))
      ->appendChild($body)
      ->setSubmitURI('/repository/delete/'.$this->id.'/')
      ->addSubmitButton(pht('Okay'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
