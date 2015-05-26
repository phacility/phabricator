<?php

final class HarbormasterStepDeleteController extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

    $id = $this->id;

    $step = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if ($step === null) {
      throw new Exception(pht('Build step not found!'));
    }

    $plan_id = $step->getBuildPlan()->getID();
    $done_uri = $this->getApplicationURI('plan/'.$plan_id.'/');

    if ($request->isDialogFormPost()) {
      $step->delete();
      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $dialog = new AphrontDialogView();
    $dialog->setTitle(pht('Really Delete Step?'))
            ->setUser($viewer)
            ->addSubmitButton(pht('Delete Build Step'))
            ->addCancelButton($done_uri);
    $dialog->appendChild(
      phutil_tag(
        'p',
        array(),
        pht(
          "Are you sure you want to delete this step? ".
          "This can't be undone!")));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
