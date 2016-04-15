<?php

final class PhabricatorConfigClusterNotificationsController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $nav = $this->buildSideNavView();
    $nav->selectFilter('cluster/notifications/');

    $title = pht('Cluster Notifications');

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addTextCrumb(pht('Cluster Notifications'));

    $notification_status = $this->buildClusterNotificationStatus();

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn($notification_status);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildClusterNotificationStatus() {
    $viewer = $this->getViewer();

    $servers = PhabricatorNotificationServerRef::newRefs();
    Javelin::initBehavior('phabricator-tooltips');

    $rows = array();
    foreach ($servers as $server) {
      if ($server->isAdminServer()) {
        $type_icon = 'fa-database sky';
        $type_tip = pht('Admin Server');
      } else {
        $type_icon = 'fa-bell sky';
        $type_tip = pht('Client Server');
      }

      $type_icon = id(new PHUIIconView())
        ->setIcon($type_icon)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $type_tip,
          ));

      $messages = array();

      $details = array();
      if ($server->isAdminServer()) {
        try {
          $details = $server->loadServerStatus();
          $status_icon = 'fa-exchange green';
          $status_label = pht('Version %s', idx($details, 'version'));
        } catch (Exception $ex) {
          $status_icon = 'fa-times red';
          $status_label = pht('Connection Error');
          $messages[] = $ex->getMessage();
        }
      } else {
        try {
          $server->testClient();
          $status_icon = 'fa-exchange green';
          $status_label = pht('Connected');
        } catch (Exception $ex) {
          $status_icon = 'fa-times red';
          $status_label = pht('Connection Error');
          $messages[] = $ex->getMessage();
        }
      }

      if ($details) {
        $uptime = idx($details, 'uptime');
        $uptime = $uptime / 1000;
        $uptime = phutil_format_relative_time_detailed($uptime);

        $clients = pht(
          '%s Active / %s Total',
          new PhutilNumber(idx($details, 'clients.active')),
          new PhutilNumber(idx($details, 'clients.total')));

        $stats = pht(
          '%s In / %s Out',
          new PhutilNumber(idx($details, 'messages.in')),
          new PhutilNumber(idx($details, 'messages.out')));

      } else {
        $uptime = null;
        $clients = null;
        $stats = null;
      }

      $status_view = array(
        id(new PHUIIconView())->setIcon($status_icon),
        ' ',
        $status_label,
      );

      $messages = phutil_implode_html(phutil_tag('br'), $messages);

      $rows[] = array(
        $type_icon,
        $server->getProtocol(),
        $server->getHost(),
        $server->getPort(),
        $status_view,
        $uptime,
        $clients,
        $stats,
        $messages,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('No notification servers are configured.'))
      ->setHeaders(
        array(
          null,
          pht('Proto'),
          pht('Host'),
          pht('Port'),
          pht('Status'),
          pht('Uptime'),
          pht('Clients'),
          pht('Messages'),
          null,
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          null,
          null,
          null,
          null,
          null,
          'wide',
        ));

    $doc_href = PhabricatorEnv::getDoclink('Cluster: Notifications');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Cluster Notification Status'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setIcon('fa-book')
          ->setHref($doc_href)
          ->setTag('a')
          ->setText(pht('Documentation')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);
  }

}
