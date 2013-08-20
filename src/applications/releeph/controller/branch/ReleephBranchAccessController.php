<?php

final class ReleephBranchAccessController extends ReleephProjectController {

  private $action;

  public function willProcessRequest(array $data) {
    $this->action = $data['action'];
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $branch = $this->getReleephBranch();
    $request = $this->getRequest();

    $done_uri = $branch->getURI();

    switch ($this->action) {
      case 'close':
        $is_active = false;
        $title_text = pht('Close Branch');
        $body_text = pht(
          'Really close the branch "%s"?',
          $branch->getBasename());
        $button_text = pht('Close Branch');
        break;
      case 're-open':
        $is_active = true;
        $title_text = pht('Reopen Branch');
        $body_text = pht(
          'Really reopen the branch "%s"?',
          $branch->getBasename());
        $button_text = pht('Reopen Branch');
        break;
      default:
        throw new Exception("Unknown action '{$this->action}'!");
        break;
    }

    if ($request->isDialogFormPost()) {
      id(new ReleephBranchEditor())
        ->setActor($request->getUser())
        ->setReleephBranch($branch)
        ->changeBranchAccess($is_active ? 1 : 0);

      return id(new AphrontReloadResponse())->setURI($done_uri);
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle($title_text)
      ->appendChild($body_text)
      ->addSubmitButton($button_text)
      ->addCancelButton($done_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
