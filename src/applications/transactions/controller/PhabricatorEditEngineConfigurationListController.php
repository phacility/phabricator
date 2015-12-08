<?php

final class PhabricatorEditEngineConfigurationListController
  extends PhabricatorEditEngineController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $engine = PhabricatorEditEngine::getByKey($viewer, $engine_key);

    $items = array();
    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Form Order'));

    $sort_create_uri = "/transactions/editengine/{$engine_key}/sort/create/";
    $sort_edit_uri = "/transactions/editengine/{$engine_key}/sort/edit/";

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $engine,
      PhabricatorPolicyCapability::CAN_EDIT);

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setName(pht('Reorder Create Forms'))
      ->setHref($sort_create_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setName(pht('Reorder Edit Forms'))
      ->setHref($sort_edit_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    return id(new PhabricatorEditEngineConfigurationSearchEngine())
      ->setController($this)
      ->setEngineKey($this->getEngineKey())
      ->setNavigationItems($items)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $viewer = $this->getViewer();
    $crumbs = parent::buildApplicationCrumbs();

    $target_key = $this->getEngineKey();
    $target_engine = PhabricatorEditEngine::getByKey($viewer, $target_key);

    id(new PhabricatorEditEngineConfigurationEditEngine())
      ->setTargetEngine($target_engine)
      ->setViewer($viewer)
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
