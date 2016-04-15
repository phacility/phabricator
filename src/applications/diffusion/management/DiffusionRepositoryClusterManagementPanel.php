<?php

final class DiffusionRepositoryClusterManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'cluster';

  public function getManagementPanelLabel() {
    return pht('Cluster Configuration');
  }

  public function getManagementPanelOrder() {
    return 12345;
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $service_phid = $repository->getAlmanacServicePHID();
    if ($service_phid) {
      $service = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withServiceTypes(
          array(
            AlmanacClusterRepositoryServiceType::SERVICETYPE,
          ))
        ->withPHIDs(array($service_phid))
        ->needBindings(true)
        ->executeOne();
      if (!$service) {
        // TODO: Viewer may not have permission to see the service, or it may
        // be invalid? Raise some more useful error here?
        throw new Exception(pht('Unable to load cluster service.'));
      }
    } else {
      $service = null;
    }

    Javelin::initBehavior('phabricator-tooltips');

    $rows = array();
    if ($service) {
      $bindings = $service->getBindings();
      $bindings = mgroup($bindings, 'getDevicePHID');

      // This is an unusual read which always comes from the master.
      if (PhabricatorEnv::isReadOnly()) {
        $versions = array();
      } else {
        $versions = PhabricatorRepositoryWorkingCopyVersion::loadVersions(
          $repository->getPHID());
      }

      $versions = mpull($versions, null, 'getDevicePHID');

      foreach ($bindings as $binding_group) {
        $all_disabled = true;
        foreach ($binding_group as $binding) {
          if (!$binding->getIsDisabled()) {
            $all_disabled = false;
            break;
          }
        }

        $any_binding = head($binding_group);

        if ($all_disabled) {
          $binding_icon = 'fa-times grey';
          $binding_tip = pht('Disabled');
        } else {
          $binding_icon = 'fa-folder-open green';
          $binding_tip = pht('Active');
        }

        $binding_icon = id(new PHUIIconView())
          ->setIcon($binding_icon)
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => $binding_tip,
            ));

        $device = $any_binding->getDevice();

        $version = idx($versions, $device->getPHID());
        if ($version) {
          $version_number = $version->getRepositoryVersion();
          $version_number = phutil_tag(
            'a',
            array(
              'href' => "/diffusion/pushlog/view/{$version_number}/",
            ),
            $version_number);
        } else {
          $version_number = '-';
        }

        if ($version && $version->getIsWriting()) {
          $is_writing = id(new PHUIIconView())
            ->setIcon('fa-pencil green');
        } else {
          $is_writing = id(new PHUIIconView())
            ->setIcon('fa-pencil grey');
        }

        $rows[] = array(
          $binding_icon,
          phutil_tag(
            'a',
            array(
              'href' => $device->getURI(),
            ),
            $device->getName()),
          $version_number,
          $is_writing,
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This is not a cluster repository.'))
      ->setHeaders(
        array(
          null,
          pht('Device'),
          pht('Version'),
          pht('Writing'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'right wide',
        ));

    $doc_href = PhabricatorEnv::getDoclink('Cluster: Repositories');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Cluster Status'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setIcon('fa-book')
          ->setHref($doc_href)
          ->setTag('a')
          ->setText(pht('Documentation')));

    if ($service) {
      $header->setSubheader(
        pht(
          'This repository is hosted on %s.',
          phutil_tag(
            'a',
            array(
              'href' => $service->getURI(),
            ),
            $service->getName())));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);
  }

}
