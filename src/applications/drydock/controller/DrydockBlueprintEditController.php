<?php

final class DrydockBlueprintEditController extends DrydockController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $blueprint = id(new DrydockBlueprintQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$blueprint) {
        return new Aphront404Response();
      }
    } else {
      $blueprint = new DrydockBlueprint();
    }

    if ($request->isFormPost()) {
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');

      // TODO: Should we use transactions here?
      $blueprint->setViewPolicy($v_view_policy);
      $blueprint->setEditPolicy($v_edit_policy);

      $blueprint->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/drydock/blueprint/');
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($blueprint)
      ->execute();

    if ($request->isAjax()) {
      $form = id(new PHUIFormLayoutView())
        ->setUser($viewer);
    } else {
      $form = id(new AphrontFormView())
        ->setUser($viewer);
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('className')
          ->setLabel(pht('Implementation'))
          ->setValue($blueprint->getClassName())
          ->setDisabled(true))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($blueprint)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($blueprint)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies));

    $crumbs = $this->buildApplicationCrumbs();

    $title = pht('Edit Blueprint');
    $header = pht('Edit Blueprint %d', $blueprint->getID());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Blueprint %d', $blueprint->getID())));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit')));

    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle($title)
        ->appendChild($form)
        ->addSubmitButton(pht('Edit Blueprint'))
        ->addCancelButton($this->getApplicationURI());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton($this->getApplicationURI()));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
