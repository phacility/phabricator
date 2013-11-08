<?php

final class NuanceSourceEditController extends NuanceController {

  private $sourceID;

  public function setSourceID($source_id) {
    $this->sourceID = $source_id;
    return $this;
  }
  public function getSourceID() {
    return $this->sourceID;
  }

  public function willProcessRequest(array $data) {
    $this->setSourceID(idx($data, 'id'));
  }

  public function processRequest() {
    $can_edit = $this->requireApplicationCapability(
      NuanceCapabilitySourceManage::CAPABILITY);

    $request = $this->getRequest();
    $user = $request->getUser();

    $source_id = $this->getSourceID();
    $is_new = !$source_id;

    if ($is_new) {
      $source = NuanceSource::initializeNewSource($user);
      $title = pht('Create Source');
    } else {
      $source = id(new NuanceSourceQuery())
        ->setViewer($user)
        ->withIDs(array($source_id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      $title = pht('Edit Source');
    }

    if (!$source) {
      return new Aphront404Response();
    }

    $error_view = null;
    $e_name = null;
    if ($request->isFormPost()) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('This does not work at all yet.'));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($source)
      ->execute();

    $crumbs = $this->buildApplicationCrumbs();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setName('name')
        ->setError($e_name)
        ->setValue($source->getName()))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Type'))
        ->setName('type')
        ->setOptions(NuanceSourceType::getSelectOptions())
        ->setValue($source->getType()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicyObject($source)
        ->setPolicies($policies)
        ->setName('viewPolicy'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setUser($user)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicyObject($source)
        ->setPolicies($policies)
        ->setName('editPolicy'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Save')));

    $layout = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($error_view)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $layout,
      ),
      array(
        'title' => $title,
        'device' => true));
  }
}
