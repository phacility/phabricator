<?php

final class HarbormasterBuildableApplyController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if ($buildable === null) {
      throw new Exception("Buildable not found!");
    }

    $buildable_uri = '/B'.$buildable->getID();

    if ($request->isDialogFormPost()) {
      $plan = id(new HarbormasterBuildPlanQuery())
        ->setViewer($viewer)
        ->withIDs(array($request->getInt('build-plan')))
        ->executeOne();

      HarbormasterBuildable::applyBuildPlans(
        $buildable->getBuildablePHID(),
        $buildable->getContainerPHID(),
        array($plan->getPHID()));

      return id(new AphrontRedirectResponse())->setURI($buildable_uri);
    }

    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->execute();

    $options = array();
    foreach ($plans as $plan) {
      $options[$plan->getID()] = $plan->getName();
    }

    // FIXME: I'd really like to use the dialog that "Edit Differential
    // Revisions" uses, but that code is quite hard-coded for the particular
    // uses, so for now we just give a single dropdown.

    $dialog = new AphrontDialogView();
    $dialog->setTitle(pht('Apply which plan?'))
      ->setUser($viewer)
      ->addSubmitButton(pht('Apply'))
      ->addCancelButton($buildable_uri);
    $dialog->appendChild(
      phutil_tag(
        'p',
        array(),
        pht(
          'Select what build plan you want to apply to this buildable:')));
    $dialog->appendChild(
      id(new AphrontFormSelectControl())
        ->setUser($viewer)
        ->setName('build-plan')
        ->setOptions($options));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
