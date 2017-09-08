<?php

final class PhabricatorConfigClusterSearchController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $nav = $this->buildSideNavView();
    $nav->selectFilter('cluster/search/');

    $title = pht('Cluster Search');
    $doc_href = PhabricatorEnv::getDoclink('Cluster: Search');

    $button = id(new PHUIButtonView())
      ->setIcon('fa-book')
      ->setHref($doc_href)
      ->setTag('a')
      ->setText(pht('Documentation'));

    $header = $this->buildHeaderView($title, $button);

    $search_status = $this->buildClusterSearchStatus();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($search_status);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  private function buildClusterSearchStatus() {
    $viewer = $this->getViewer();

    $services = PhabricatorSearchService::getAllServices();
    Javelin::initBehavior('phabricator-tooltips');

    $view = array();
    foreach ($services as $service) {
      $view[] = $this->renderStatusView($service);
    }
    return $view;
  }

  private function renderStatusView($service) {
    $head = array_merge(
        array(pht('Type')),
        array_keys($service->getStatusViewColumns()),
        array(pht('Status')));

    $rows = array();

    $status_map = PhabricatorSearchService::getConnectionStatusMap();
    $stats = false;
    $stats_view = false;

    foreach ($service->getHosts() as $host) {
      try {
        $status = $host->getConnectionStatus();
        $status = idx($status_map, $status, array());
      } catch (Exception $ex) {
        $status['icon'] = 'fa-times';
        $status['label'] = pht('Connection Error');
        $status['color'] = 'red';
        $host->didHealthCheck(false);
      }

      if (!$stats_view) {
        try {
          $stats = $host->getEngine()->getIndexStats($host);
          $stats_view = $this->renderIndexStats($stats);
        } catch (Exception $e) {
          $stats_view = false;
        }
      }

      $type_icon = 'fa-search sky';
      $type_tip = $host->getDisplayName();

      $type_icon = id(new PHUIIconView())
        ->setIcon($type_icon);
      $status_view = array(
        id(new PHUIIconView())->setIcon($status['icon'].' '.$status['color']),
        ' ',
        $status['label'],
      );
      $row = array(array($type_icon, ' ', $type_tip));
      $row = array_merge($row, array_values(
        $host->getStatusViewColumns()));
      $row[] = $status_view;
      $rows[] = $row;
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No search servers are configured.'))
      ->setHeaders($head);

    $view = $this->buildConfigBoxView(pht('Search Servers'), $table);

    $stats = null;
    if ($stats_view->hasAnyProperties()) {
      $stats = $this->buildConfigBoxView(
        pht('%s Stats', $service->getDisplayName()),
        $stats_view);
    }

    return array($stats, $view);
  }

  private function renderIndexStats($stats) {
    $view = id(new PHUIPropertyListView());
    if ($stats !== false) {
      foreach ($stats as $label => $val) {
        $view->addProperty($label, $val);
      }
    }
    return $view;
  }

}
