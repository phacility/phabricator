<?php

final class HarbormasterPlanRunController extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

    $plan_id = $request->getURIData('id');

    // NOTE: At least for now, this only requires the "Can Manage Plans"
    // capability, not the "Can Edit" capability. Possibly it should have
    // a more stringent requirement, though.

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($plan_id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI("plan/{$plan_id}/");

    if (!$plan->canRunManually()) {
      return $this->newDialog()
        ->setTitle(pht('Can Not Run Plan'))
        ->appendParagraph(pht('This plan can not be run manually.'))
        ->addCancelButton($cancel_uri);
    }

    $e_name = true;
    $v_name = null;

    $errors = array();
    if ($request->isFormPost()) {
      $buildable = HarbormasterBuildable::initializeNewBuildable($viewer)
        ->setIsManualBuildable(true);

      $v_name = $request->getStr('buildablePHID');

      if ($v_name) {
        $object = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withNames(array($v_name))
          ->executeOne();

        if ($object instanceof HarbormasterBuildableInterface) {
          $buildable
            ->setBuildablePHID($object->getHarbormasterBuildablePHID())
            ->setContainerPHID($object->getHarbormasterContainerPHID());
        } else {
          $e_name = pht('Invalid');
          $errors[] = pht('Enter the name of a revision or commit.');
        }
      } else {
        $e_name = pht('Required');
        $errors[] = pht('You must choose a revision or commit to build.');
      }

      if (!$errors) {
        $buildable->save();
        $buildable->applyPlan($plan, array());

        $buildable_uri = '/B'.$buildable->getID();
        return id(new AphrontRedirectResponse())->setURI($buildable_uri);
      }
    }

    if ($errors) {
      $errors = id(new PHUIInfoView())->setErrors($errors);
    }

    $title = pht('Run Build Plan Manually');
    $save_button = pht('Run Plan Manually');

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          "Enter the name of a commit or revision to run this plan on (for ".
          "example, `rX123456` or `D123`).\n\n".
          "For more detailed output, you can also run manual builds from ".
          "the command line:\n\n".
          "  phabricator/ $ ./bin/harbormaster build <object> --plan %s",
          $plan->getID()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Buildable Name'))
          ->setName('buildablePHID')
          ->setError($e_name)
          ->setValue($v_name));

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setTitle($title)
      ->appendChild($form)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton($save_button);
  }

}
