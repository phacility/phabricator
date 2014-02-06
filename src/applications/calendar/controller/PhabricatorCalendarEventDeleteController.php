<?php

final class PhabricatorCalendarEventDeleteController
  extends PhabricatorCalendarController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request  = $this->getRequest();
    $user     = $request->getUser();

    $status = id(new PhabricatorCalendarEventQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$status) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $status->delete();
      $uri = new PhutilURI($this->getApplicationURI());
      $uri->setQueryParams(
        array(
          'deleted' => true,
        ));
      return id(new AphrontRedirectResponse())
        ->setURI($uri);
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);
    $dialog->setTitle(pht('Really delete status?'));
    $dialog->appendChild(
      pht('Permanently delete this status? This action can not be undone.'));
    $dialog->addSubmitButton(pht('Delete'));
    $dialog->addCancelButton(
      $this->getApplicationURI('event/'));

    return id(new AphrontDialogResponse())->setDialog($dialog);

  }

}
