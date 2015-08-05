<?php

final class NuanceSourceEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $can_edit = $this->requireApplicationCapability(
      NuanceSourceManageCapability::CAPABILITY);

    $viewer = $this->getViewer();

    $sources_uri = $this->getApplicationURI('source/');

    $source_id = $request->getURIData('id');
    $is_new = !$source_id;

    if ($is_new) {
      $source = NuanceSource::initializeNewSource($viewer);

      $type = $request->getURIData('type');
      $map = NuanceSourceDefinition::getAllDefinitions();

      if (empty($map[$type])) {
        return new Aphront404Response();
      }

      $source->setType($type);
      $cancel_uri = $sources_uri;
    } else {
      $source = id(new NuanceSourceQuery())
        ->setViewer($viewer)
        ->withIDs(array($source_id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$source) {
        return new Aphront404Response();
      }
      $cancel_uri = $source->getURI();
    }

    $definition = NuanceSourceDefinition::getDefinitionForSource($source);
    $definition->setActor($viewer);

    $response = $definition->buildEditLayout($request);
    if ($response instanceof AphrontResponse) {
      return $response;
    }
    $layout = $response;

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Sources'), $sources_uri);

    if ($is_new) {
      $crumbs->addTextCrumb(pht('New'));
    } else {
      $crumbs->addTextCrumb($source->getName(), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $layout,
      ),
      array(
        'title' => $definition->getEditTitle(),
      ));
  }
}
