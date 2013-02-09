<?php

final class DifferentialSubscribeController extends DifferentialController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $revision = id(new DifferentialRevision())->load($this->id);
    if (!$revision) {
      return new Aphront404Response();
    }

    if (!$request->isFormPost()) {
      $dialog = new AphrontDialogView();

      switch ($this->action) {
        case 'add':
          $button = pht('Subscribe');
          $title = pht('Subscribe to Revision');
          $prompt = pht('Really subscribe to this revision?');
          break;
        case 'rem':
          $button = pht('Unsubscribe');
          $title = pht('Unsubscribe from Revision');
          $prompt = pht('Really unsubscribe from this revision? Herald will '.
                    'not resubscribe you to a revision you unsubscribe '.
                    'from.');
          break;
        default:
          return new Aphront400Response();
      }

      $dialog
        ->setUser($user)
        ->setTitle($title)
        ->appendChild(phutil_tag('p', array(), $prompt))
        ->setSubmitURI($request->getRequestURI())
        ->addSubmitButton($button)
        ->addCancelButton('/D'.$revision->getID());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $revision->loadRelationships();
    $phid = $user->getPHID();

    switch ($this->action) {
      case 'add':
        DifferentialRevisionEditor::addCCAndUpdateRevision(
          $revision,
          $phid,
          $user);
        break;
      case 'rem':
        DifferentialRevisionEditor::removeCCAndUpdateRevision(
          $revision,
          $phid,
          $user);
        break;
      default:
        return new Aphront400Response();
    }

    return id(new AphrontRedirectResponse())->setURI('/D'.$revision->getID());
  }
}
