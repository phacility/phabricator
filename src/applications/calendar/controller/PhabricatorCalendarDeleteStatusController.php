<?php

final class PhabricatorCalendarDeleteStatusController
  extends PhabricatorCalendarController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request  = $this->getRequest();
    $user     = $request->getUser();
    $status   = id(new PhabricatorUserStatus())
      ->loadOneWhere('id = %d', $this->id);

    if (!$status) {
      return new Aphront404Response();
    }
    if ($status->getUserPHID() != $user->getPHID()) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $status->delete();
      $uri = new PhutilURI($this->getApplicationURI());
      $uri->setQueryParams(
        array(
          'deleted' => true,
        )
      );
      return id(new AphrontRedirectResponse())
        ->setURI($uri);
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);
    $dialog->setTitle(pht('Really delete status?'));
    $dialog->appendChild(phutil_render_tag(
      'p',
      array(),
      pht('Permanently delete this status? This action can not be undone.')
    ));
    $dialog->addSubmitButton(pht('Delete'));
    $dialog->addCancelButton(
      $this->getApplicationURI('status/edit/'.$status->getID().'/')
    );

    return id(new AphrontDialogResponse())->setDialog($dialog);

  }

}
