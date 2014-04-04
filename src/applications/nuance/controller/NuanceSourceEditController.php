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
    }

    if (!$source) {
      return new Aphront404Response();
    }

    $definition = NuanceSourceDefinition::getDefinitionForSource($source);
    $definition->setActor($user);

    $response = $definition->buildEditLayout($request);
    if ($response instanceof AphrontResponse) {
      return $response;
    }
    $layout = $response;

    $crumbs = $this->buildApplicationCrumbs();
    return $this->buildApplicationPage(
      array(
        $crumbs,
        $layout,
      ),
      array(
        'title' => $definition->getEditTitle(),
        'device' => true));
  }
}
