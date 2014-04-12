<?php

final class ReleephBranchAccessController extends ReleephBranchController {

  private $action;
  private $branchID;

  public function willProcessRequest(array $data) {
    $this->action = $data['action'];
    $this->branchID = $data['branchID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->branchID))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$branch) {
      return new Aphront404Response();
    }
    $this->setBranch($branch);

    $action = $this->action;
    switch ($action) {
      case 'close':
      case 're-open':
        break;
      default:
        return new Aphront404Response();
    }

    $branch_uri = $this->getBranchViewURI($branch);
    if ($request->isFormPost()) {

      if ($action == 're-open') {
        $is_active = 1;
      } else {
        $is_active = 0;
      }

      id(new ReleephBranchEditor())
        ->setActor($request->getUser())
        ->setReleephBranch($branch)
        ->changeBranchAccess($is_active);

      return id(new AphrontReloadResponse())->setURI($branch_uri);
    }

    if ($action == 'close') {
      $title_text = pht('Really Close Branch?');
      $short = pht('Close Branch');
      $body_text = pht(
        'Really close the branch "%s"?',
        phutil_tag('strong', array(), $branch->getBasename()));
      $button_text = pht('Close Branch');
    } else {
      $title_text = pht('Really Reopen Branch?');
      $short = pht('Reopen Branch');
      $body_text = pht(
        'Really reopen the branch "%s"?',
        phutil_tag('strong', array(), $branch->getBasename()));
      $button_text = pht('Reopen Branch');
    }

    return $this->newDialog()
      ->setTitle($title_text)
      ->setShortTitle($short)
      ->appendChild($body_text)
      ->addSubmitButton($button_text)
      ->addCancelButton($branch_uri);
  }

}
