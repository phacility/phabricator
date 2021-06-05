<?php

final class DiffusionRepositoryStorageManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'storage';

  public function getManagementPanelLabel() {
    return pht('Storage');
  }

  public function getManagementPanelOrder() {
    return 600;
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    if ($repository->getAlmanacServicePHID()) {
      return 'fa-sitemap';
    } else if ($repository->isHosted()) {
      return 'fa-database';
    } else {
      return 'fa-download';
    }
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $doc_href = PhabricatorEnv::getDoclink('Cluster: Repositories');

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-book')
        ->setHref($doc_href)
        ->setName(pht('Cluster Documentation')));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    return array(
      $this->buildStorageStatusPanel(),
      $this->buildClusterStatusPanel(),
      $this->buildRefsStatusPanels(),
    );
  }

  private function buildStorageStatusPanel() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    if ($repository->usesLocalWorkingCopy()) {
      $storage_path = $repository->getLocalPath();
    } else {
      $storage_path = phutil_tag('em', array(), pht('No Local Working Copy'));
    }

    $service_phid = $repository->getAlmanacServicePHID();
    if ($service_phid) {
      $storage_service = $viewer->renderHandle($service_phid);
    } else {
      $storage_service = phutil_tag('em', array(), pht('Local'));
    }

    $view->addProperty(pht('Storage Path'), $storage_path);
    $view->addProperty(pht('Storage Cluster'), $storage_service);

    return $this->newBox(pht('Storage'), $view);
  }

  private function buildClusterStatusPanel() {
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
        ->needActiveBindings(true)
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
      $bindings = $service->getActiveBindings();
      $bindings = mgroup($bindings, 'getDevicePHID');

      // This is an unusual read which always comes from the master.
      if (PhabricatorEnv::isReadOnly()) {
        $versions = array();
      } else {
        $versions = PhabricatorRepositoryWorkingCopyVersion::loadVersions(
          $repository->getPHID());
      }

      $versions = mpull($versions, null, 'getDevicePHID');

      $sort = array();
      foreach ($bindings as $key => $binding_group) {
        $sort[$key] = id(new PhutilSortVector())
          ->addString(head($binding_group)->getDevice()->getName());
      }
      $sort = msortv($sort, 'getSelf');
      $bindings = array_select_keys($bindings, array_keys($sort)) + $bindings;

      foreach ($bindings as $binding_group) {
        $any_binding = head($binding_group);

        $binding_icon = 'fa-folder-open green';
        $binding_tip = pht('Active');

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

          $href = null;
          if ($repository->isHosted()) {
            $href = "/diffusion/pushlog/view/{$version_number}/";
          } else {
            $commit = id(new DiffusionCommitQuery())
              ->setViewer($viewer)
              ->withIDs(array($version_number))
              ->executeOne();
            if ($commit) {
              $href = $commit->getURI();
            }
          }

          if ($href) {
            $version_number = phutil_tag(
              'a',
              array(
                'href' => $href,
              ),
              $version_number);
          }
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

        $write_properties = null;
        if ($version) {
          $write_properties = $version->getWriteProperties();
          if ($write_properties) {
            try {
              $write_properties = phutil_json_decode($write_properties);
            } catch (Exception $ex) {
              $write_properties = null;
            }
          }
        }

        $last_writer = null;
        $writer_epoch = null;
        if ($write_properties) {
          $writer_phid = idx($write_properties, 'userPHID');

          if ($writer_phid) {
            $last_writer = $viewer->renderHandle($writer_phid);
          }

          $writer_epoch = idx($write_properties, 'epoch');
          if ($writer_epoch) {
            $writer_epoch = phabricator_datetime($writer_epoch, $viewer);
          }
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
          $last_writer,
          $writer_epoch,
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
          pht('Last Writer'),
          pht('Last Write At'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'right wide',
          null,
          'date',
        ));

    return $this->newBox(pht('Cluster Status'), $table);
  }

  private function buildRefsStatusPanels() {
    $repository = $this->getRepository();

    $service_phid = $repository->getAlmanacServicePHID();
    if (!$service_phid) {
      // If this repository isn't clustered, don't bother rendering anything.
      // There are enough other context clues that another empty panel isn't
      // useful.
      return;
    }

    $all_protocols = array(
      'http',
      'https',
      'ssh',
    );

    $readable_panel = $this->buildRefsStatusPanel(
      pht('Readable Service Refs'),
      array(
        'neverProxy' => false,
        'protocols' => $all_protocols,
        'writable' => false,
      ));

    $writable_panel = $this->buildRefsStatusPanel(
      pht('Writable Service Refs'),
      array(
        'neverProxy' => false,
        'protocols' => $all_protocols,
        'writable' => true,
      ));

    return array(
      $readable_panel,
      $writable_panel,
    );
  }

  private function buildRefsStatusPanel(
    $title,
    $options) {

    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $caught = null;
    try {
      $refs = $repository->getAlmanacServiceRefs($viewer, $options);
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    $info_view = null;
    if ($caught) {
      $refs = array();
      $info_view = id(new PHUIInfoView())
        ->setErrors(
          array(
            phutil_escape_html_newlines($caught->getMessage()),
          ));
    }

    $phids = array();
    foreach ($refs as $ref) {
      $phids[] = $ref->getDevicePHID();
    }

    $handles = $viewer->loadHandles($phids);

    $icon_writable = id(new PHUIIconView())
      ->setIcon('fa-pencil', 'green');

    $icon_unwritable = id(new PHUIIconView())
      ->setIcon('fa-times', 'grey');

    $rows = array();
    foreach ($refs as $ref) {
      $device_phid = $ref->getDevicePHID();
      $device_handle = $handles[$device_phid];

      if ($ref->isWritable()) {
        $writable_icon = $icon_writable;
        $writable_text = pht('Read/Write');
      } else {
        $writable_icon = $icon_unwritable;
        $writable_text = pht('Read Only');
      }

      $rows[] = array(
        $device_handle->renderLink(),
        $ref->getURI(),
        $writable_icon,
        $writable_text,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No repository service refs available.'))
      ->setHeaders(
        array(
          pht('Device'),
          pht('Internal Service URI'),
          null,
          pht('I/O'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide',
          'icon',
          null,
        ));

    $box_view = $this->newBox($title, $table);

    if ($info_view) {
      $box_view->setInfoView($info_view);
    }

    return $box_view;
  }

}
