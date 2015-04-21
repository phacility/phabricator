<?php

final class PhabricatorDashboardQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  private $needPanels;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function needPanels($need_panels) {
    $this->needPanels = $need_panels;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorDashboard();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function didFilterPage(array $dashboards) {
    if ($this->needPanels) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($dashboards, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      $panel_phids = $edge_query->getDestinationPHIDs();
      if ($panel_phids) {
        $panels = id(new PhabricatorDashboardPanelQuery())
          ->setParentQuery($this)
          ->setViewer($this->getViewer())
          ->withPHIDs($panel_phids)
          ->execute();
        $panels = mpull($panels, null, 'getPHID');
      } else {
        $panels = array();
      }

      foreach ($dashboards as $dashboard) {
        $dashboard_phids = $edge_query->getDestinationPHIDs(
          array($dashboard->getPHID()));
        $dashboard_panels = array_select_keys($panels, $dashboard_phids);

        $dashboard->attachPanelPHIDs($dashboard_phids);
        $dashboard->attachPanels($dashboard_panels);
      }
    }

    return $dashboards;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

}
