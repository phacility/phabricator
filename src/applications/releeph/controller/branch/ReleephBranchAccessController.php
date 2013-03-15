<?php

final class ReleephBranchAccessController extends ReleephController {

  private $action;

  public function willProcessRequest(array $data) {
    $this->action = $data['action'];
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $rph_branch = $this->getReleephBranch();
    $request = $this->getRequest();

    $active_uri = '/releeph/project/'.$rph_branch->getReleephProjectID().'/';
    $inactive_uri = $active_uri.'inactive/';

    switch ($this->action) {
      case 'close':
        $is_active = false;
        $origin_uri = $active_uri;
        break;

      case 're-open':
        $is_active = true;
        $origin_uri = $inactive_uri;
        break;

      default:
        throw new Exception("Unknown action '{$this->action}'!");
        break;
    }

    if ($request->isDialogFormPost()) {
      id(new ReleephBranchEditor())
        ->setActor($request->getUser())
        ->setReleephBranch($rph_branch)
        ->changeBranchAccess($is_active ? 1 : 0);
      return id(new AphrontRedirectResponse())
        ->setURI($origin_uri);
    }

    $button_text = ucfirst($this->action).' Branch';
    $message = hsprintf(
      '<p>Really %s the branch <i>%s</i>?</p>',
      $this->action,
      $rph_branch->getBasename());


    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle('Confirm')
      ->appendChild($message)
      ->addSubmitButton($button_text)
      ->addCancelButton($origin_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
