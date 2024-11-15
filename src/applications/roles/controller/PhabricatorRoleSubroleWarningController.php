<?php

final class PhabricatorRoleSubroleWarningController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $role = $this->getRole();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    if (!$can_edit) {
      return new Aphront404Response();
    }

    $id = $role->getID();
    $cancel_uri = "/role/subroles/{$id}/";
    $done_uri = "/role/edit/?parent={$id}";

    if ($request->isFormPost()) {
      return id(new AphrontRedirectResponse())
        ->setURI($done_uri);
    }

    $doc_href = PhabricatorEnv::getDoclink('Roles User Guide');

    $conversion_help = pht(
      "Creating a role's first subrole **moves all ".
      "members** to become members of the subrole instead.".
      "\n\n".
      "See [[ %s | Roles User Guide ]] in the documentation for details. ".
      "This process can not be undone.",
      $doc_href);

    return $this->newDialog()
      ->setTitle(pht('Convert to Parent Role'))
      ->appendChild(new PHUIRemarkupView($viewer, $conversion_help))
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Convert Role'));
  }

}
