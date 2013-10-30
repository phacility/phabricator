<?php

final class DiffusionRepositoryEditPolicyController
  extends DiffusionRepositoryEditController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($repository->getID()))
      ->executeOne();

    if (!$repository) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_view = $repository->getViewPolicy();
    $v_edit = $repository->getEditPolicy();
    $v_push = $repository->getPushPolicy();

    if ($request->isFormPost()) {
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_push = $request->getStr('pushPolicy');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;
      $type_push = PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(clone $template)
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      if ($repository->isHosted()) {
        $xactions[] = id(clone $template)
          ->setTransactionType($type_push)
          ->setNewValue($v_push);
      }

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $content = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Policies')));

    $title = pht('Edit Policies (%s)', $repository->getName());

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($repository)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($repository)
          ->setPolicies($policies)
          ->setName('viewPolicy'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicyObject($repository)
          ->setPolicies($policies)
          ->setName('editPolicy'));

    if ($repository->isHosted()) {
      $form->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(DiffusionCapabilityPush::CAPABILITY)
          ->setPolicyObject($repository)
          ->setPolicies($policies)
          ->setName('pushPolicy'));
    } else {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Can Push'))
          ->setValue(
            phutil_tag('em', array(), pht('Not a Hosted Repository'))));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Policies'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
