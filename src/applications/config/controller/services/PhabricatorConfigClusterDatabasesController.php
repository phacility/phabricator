<?php

final class PhabricatorConfigClusterDatabasesController
  extends PhabricatorConfigServicesController {

  public function handleRequest(AphrontRequest $request) {
    $nav = $this->newNavigation('database-servers');

    $title = pht('Database Servers');
    $doc_href = PhabricatorEnv::getDoclink('Cluster: Databases');
    $button = id(new PHUIButtonView())
      ->setIcon('fa-book')
      ->setHref($doc_href)
      ->setTag('a')
      ->setText(pht('Documentation'));

    $header = $this->buildHeaderView($title, $button);

    $database_status = $this->buildClusterDatabaseStatus();
    $status = $this->buildConfigBoxView(pht('Status'), $database_status);

    $crumbs = $this->newCrumbs()
      ->addTextCrumb($title);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($status);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content);
  }

  private function buildClusterDatabaseStatus() {
    $viewer = $this->getViewer();

    $databases = PhabricatorDatabaseRef::queryAll();
    $connection_map = PhabricatorDatabaseRef::getConnectionStatusMap();
    $replica_map = PhabricatorDatabaseRef::getReplicaStatusMap();
    Javelin::initBehavior('phabricator-tooltips');

    $rows = array();
    foreach ($databases as $database) {
      $messages = array();

      if ($database->getIsMaster()) {
        $role_icon = id(new PHUIIconView())
          ->setIcon('fa-database sky')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Master'),
            ));
      } else {
        $role_icon = id(new PHUIIconView())
          ->setIcon('fa-download')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Replica'),
            ));
      }

      if ($database->getDisabled()) {
        $conn_icon = 'fa-times';
        $conn_color = 'grey';
        $conn_label = pht('Disabled');
      } else {
        $status = $database->getConnectionStatus();

        $info = idx($connection_map, $status, array());
        $conn_icon = idx($info, 'icon');
        $conn_color = idx($info, 'color');
        $conn_label = idx($info, 'label');

        if ($status === PhabricatorDatabaseRef::STATUS_OKAY) {
          $latency = $database->getConnectionLatency();
          $latency = (int)(1000000 * $latency);
          $conn_label = pht('%s us', new PhutilNumber($latency));
        }
      }

      $connection = array(
        id(new PHUIIconView())->setIcon("{$conn_icon} {$conn_color}"),
        ' ',
        $conn_label,
      );

      if ($database->getDisabled()) {
        $replica_icon = 'fa-times';
        $replica_color = 'grey';
        $replica_label = pht('Disabled');
      } else {
        $status = $database->getReplicaStatus();

        $info = idx($replica_map, $status, array());
        $replica_icon = idx($info, 'icon');
        $replica_color = idx($info, 'color');
        $replica_label = idx($info, 'label');

        if ($database->getIsMaster()) {
          if ($status === PhabricatorDatabaseRef::REPLICATION_OKAY) {
            $replica_icon = 'fa-database';
          }
        } else {
          switch ($status) {
            case PhabricatorDatabaseRef::REPLICATION_OKAY:
            case PhabricatorDatabaseRef::REPLICATION_SLOW:
              $delay = $database->getReplicaDelay();
              if ($delay) {
                $replica_label = pht('%ss Behind', new PhutilNumber($delay));
              } else {
                $replica_label = pht('Up to Date');
              }
              break;
          }
        }
      }

      $replication = array(
        id(new PHUIIconView())->setIcon("{$replica_icon} {$replica_color}"),
        ' ',
        $replica_label,
      );

      $health = $database->getHealthRecord();
      $health_up = $health->getUpEventCount();
      $health_down = $health->getDownEventCount();

      if ($health->getIsHealthy()) {
        $health_icon = id(new PHUIIconView())
          ->setIcon('fa-plus green');
      } else {
        $health_icon = id(new PHUIIconView())
          ->setIcon('fa-times red');
        $messages[] = pht(
          'UNHEALTHY: This database has failed recent health checks. Traffic '.
          'will not be sent to it until it recovers.');
      }

      $health_count = pht(
        '%s / %s',
        new PhutilNumber($health_up),
        new PhutilNumber($health_up + $health_down));

      $health_status = array(
        $health_icon,
        ' ',
        $health_count,
      );

      $conn_message = $database->getConnectionMessage();
      if ($conn_message) {
        $messages[] = $conn_message;
      }

      $replica_message = $database->getReplicaMessage();
      if ($replica_message) {
        $messages[] = $replica_message;
      }

      $messages = phutil_implode_html(phutil_tag('br'), $messages);

      $partition = null;
      if ($database->getIsMaster()) {
        if ($database->getIsDefaultPartition()) {
          $partition = id(new PHUIIconView())
            ->setIcon('fa-circle sky')
            ->addSigil('has-tooltip')
            ->setMetadata(
              array(
                'tip' => pht('Default Partition'),
              ));
        } else {
          $map = $database->getApplicationMap();
          if ($map) {
            $list = implode(', ', $map);
          } else {
            $list = pht('Empty');
          }

          $partition = id(new PHUIIconView())
            ->setIcon('fa-adjust sky')
            ->addSigil('has-tooltip')
            ->setMetadata(
              array(
                'tip' => pht('Partition: %s', $list),
              ));
        }
      }

      $rows[] = array(
        $role_icon,
        $partition,
        $database->getHost(),
        $database->getPort(),
        $database->getUser(),
        $connection,
        $replication,
        $health_status,
        $messages,
      );
    }


    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('Phabricator is not configured in cluster mode.'))
      ->setHeaders(
        array(
          null,
          null,
          pht('Host'),
          pht('Port'),
          pht('User'),
          pht('Connection'),
          pht('Replication'),
          pht('Health'),
          pht('Messages'),
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

    return $table;
  }

}
