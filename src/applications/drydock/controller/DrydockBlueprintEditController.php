<?php

final class DrydockBlueprintEditController extends DrydockBlueprintController {

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

      $impl = $blueprint->getImplementation();
      $cancel_uri = $this->getApplicationURI('blueprint/'.$this->id.'/');
    } else {
      $class = $request->getStr('class');

      $impl = DrydockBlueprintImplementation::getNamedImplementation($class);
      if (!$impl || !$impl->isEnabled()) {
        return new Aphront400Response();
      }

      $blueprint = new DrydockBlueprint();
      $blueprint->setClassName($class);
      $cancel_uri = $this->getApplicationURI('blueprint/');
    }


    if ($request->isFormPost()) {
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');

      // TODO: Should we use transactions here?
      $blueprint->setViewPolicy($v_view_policy);
      $blueprint->setEditPolicy($v_edit_policy);

      $blueprint->save();

      $id = $blueprint->getID();
      $save_uri = $this->getApplicationURI("blueprint/{$id}/");

      return id(new AphrontRedirectResponse())->setURI($save_uri);
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($blueprint)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('class', $request->getStr('class'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Blueprint Type'))
          ->setValue($impl->getBlueprintName()))
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

    if ($blueprint->getID()) {
      $title = pht('Edit Blueprint');
      $header = pht('Edit Blueprint %d', $blueprint->getID());
      $crumbs->addTextCrumb(pht('Blueprint %d', $blueprint->getID()));
      $crumbs->addTextCrumb(pht('Edit'));
      $submit = pht('Save Blueprint');
    } else {
      $title = pht('New Blueprint');
      $header = pht('New Blueprint');
      $crumbs->addTextCrumb(pht('New Blueprint'));
      $submit = pht('Create Blueprint');
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($submit)
        ->addCancelButton($cancel_uri));

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
