<?php

final class PhabricatorDashboardTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Dashboards');
  }

  public static function addPanelToDashboard(
    PhabricatorUser $actor,
    PhabricatorContentSource $content_source,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboard $dashboard,
    $column) {

    $xactions = array();
    $xactions[] = id(new PhabricatorDashboardTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array(
            $panel->getPHID() => $panel->getPHID(),
          ),
        ));

    $layout_config = $dashboard->getLayoutConfigObject();
    $layout_config->setPanelLocation($column, $panel->getPHID());
    $dashboard->setLayoutConfigFromObject($layout_config);

    $editor = id(new PhabricatorDashboardTransactionEditor())
      ->setActor($actor)
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->applyTransactions($dashboard, $xactions);
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    return $types;
  }

  protected function supportsSearch() {
    return true;
  }

}
