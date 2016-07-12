<?php

final class HarbormasterStepDeleteController
  extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');

    $step = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$step) {
      return new Aphront404Response();
    }

    $plan_id = $step->getBuildPlan()->getID();
    $done_uri = $this->getApplicationURI('plan/'.$plan_id.'/');

    if ($request->isDialogFormPost()) {
      $step->delete();
      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Really Delete Step?'))
      ->appendParagraph(
        pht(
          "Are you sure you want to delete this step? ".
          "This can't be undone!"))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Delete Build Step'));
  }

}
