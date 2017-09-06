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

  public function getManagementPanelIcon() {
    return 'fa-bullseye';
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'symbolLanguages',
      'symbolRepositoryPHIDs',
    );
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $symbols_uri = $this->getEditPageURI();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($symbols_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    return $this->newBox(pht('Symbols'), $view, array($button));
  }

}
