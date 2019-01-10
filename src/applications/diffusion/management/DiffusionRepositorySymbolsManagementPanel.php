<?php

final class DiffusionRepositorySymbolsManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'symbols';

  public function getManagementPanelLabel() {
    return pht('Symbols');
  }

  public function getManagementPanelOrder() {
    return 900;
  }

  public function getManagementPanelGroupKey() {
    return DiffusionRepositoryManagementIntegrationsPanelGroup::PANELGROUPKEY;
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $has_any =
      $repository->getSymbolLanguages() ||
      $repository->getSymbolSources();

    if ($has_any) {
      return 'fa-link';
    } else {
      return 'fa-link grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'symbolLanguages',
      'symbolRepositoryPHIDs',
    );
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $symbols_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Symbols'))
        ->setHref($symbols_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $languages = $repository->getSymbolLanguages();
    if ($languages) {
      $languages = implode(', ', $languages);
    } else {
      $languages = phutil_tag('em', array(), pht('Any'));
    }
    $view->addProperty(pht('Languages'), $languages);

    $sources = $repository->getSymbolSources();
    if ($sources) {
      $sources = $viewer->renderHandleList($sources);
    } else {
      $sources = phutil_tag('em', array(), pht('This Repository Only'));
    }
    $view->addProperty(pht('Uses Symbols From'), $sources);

    return $this->newBox(pht('Symbols'), $view);
  }

}
