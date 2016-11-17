<?php

final class PhabricatorProjectSubprojectWarningController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    if (!$can_edit) {
      return new Aphront404Response();
    }

    $id = $project->getID();
    $cancel_uri = "/project/subprojects/{$id}/";
    $done_uri = "/project/edit/?parent={$id}";

    if ($request->isFormPost()) {
      return id(new AphrontRedirectResponse())
        ->setURI($done_uri);
    }

    $doc_href = PhabricatorEnv::getDoclink('Projects User Guide');

    $conversion_help = pht(
      "Creating a project's first subproject **moves all ".
      "members** to become members of the subproject instead.".
      "\n\n".
      "See [[ %s | Projects User Guide ]] in the documentation for details. ".
      "This process can not be undone.",
      $doc_href);

    return $this->newDialog()
      ->setTitle(pht('Convert to Parent Project'))
      ->appendChild(new PHUIRemarkupView($viewer, $conversion_help))
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Convert Project'));
  }

}
