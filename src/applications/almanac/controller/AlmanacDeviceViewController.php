<?php

final class AlmanacDeviceViewController
  extends AlmanacDeviceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $name = $request->getURIData('name');

    $device = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($name))
      ->executeOne();
    if (!$device) {
      return new Aphront404Response();
    }

    // We rebuild locks on a device when viewing the detail page, so they
    // automatically get corrected if they fall out of sync.
    $device->rebuildDeviceLocks();

    $title = pht('Device %s', $device->getName());

    $property_list = $this->buildPropertyList($device);
    $action_list = $this->buildActionList($device);
    $property_list->setActionList($action_list);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($device->getName())
      ->setPolicyObject($device);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($property_list);

    if ($device->getIsLocked()) {
      $this->addLockMessage(
        $box,
        pht(
          'This device is bound to a locked service, so it can not be '.
          'edited.'));
    }

    $interfaces = $this->buildInterfaceList($device);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($device->getName());

    $timeline = $this->buildTransactionTimeline(
      $device,
      new AlmanacDeviceTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $interfaces,
        $this->buildAlmanacPropertiesTable($device),
        $this->buildSSHKeysTable($device),
        $this->buildServicesTable($device),
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyList(AlmanacDevice $device) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($device);

    return $properties;
  }

  private function buildActionList(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $id = $device->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Device'))
        ->setHref($this->getApplicationURI("device/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $actions;
  }

  private function buildInterfaceList(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $id = $device->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setViewer($viewer)
      ->withDevicePHIDs(array($device->getPHID()))
      ->execute();

    $table = id(new AlmanacInterfaceTableView())
      ->setUser($viewer)
      ->setInterfaces($interfaces)
      ->setCanEdit($can_edit);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Device Interfaces'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($this->getApplicationURI("interface/edit/?deviceID={$id}"))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit)
          ->setText(pht('Add Interface'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-plus')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);
  }

  private function buildSSHKeysTable(AlmanacDevice $device) {
    $viewer = $this->getViewer();
    $id = $device->getID();
    $device_phid = $device->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $device,
      PhabricatorPolicyCapability::CAN_EDIT);

    $keys = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($device_phid))
      ->execute();

    $table = id(new PhabricatorAuthSSHKeyTableView())
      ->setUser($viewer)
      ->setKeys($keys)
      ->setCanEdit($can_edit)
      ->setShowID(true)
      ->setShowTrusted(true)
      ->setNoDataString(pht('This device has no associated SSH public keys.'));

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();
      $can_generate = true;
    } catch (Exception $ex) {
      $can_generate = false;
    }

    $generate_uri = '/auth/sshkey/generate/?objectPHID='.$device_phid;
    $upload_uri = '/auth/sshkey/upload/?objectPHID='.$device_phid;

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('SSH Public Keys'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($generate_uri)
          ->setWorkflow(true)
          ->setDisabled(!$can_edit || !$can_generate)
          ->setText(pht('Generate Keypair'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-lock')))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($upload_uri)
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setText(pht('Upload Public Key'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-upload')));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);


  }

  private function buildServicesTable(AlmanacDevice $device) {
    $viewer = $this->getViewer();

    // NOTE: We're loading all services so we can show hidden, locked services.
    // In general, we let you know about all the things the device is bound to,
    // even if you don't have permission to see their details. This is similar
    // to exposing the existence of edges in other applications, with the
    // addition of always letting you see that locks exist.

    $services = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDevicePHIDs(array($device->getPHID()))
      ->execute();

    $handles = $viewer->loadHandles(mpull($services, 'getPHID'));

    $icon_lock = id(new PHUIIconView())
      ->setIconFont('fa-lock');

    $rows = array();
    foreach ($services as $service) {
      $rows[] = array(
        ($service->getIsLocked()
          ? $icon_lock
          : null),
        $handles->renderHandle($service->getPHID()),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No services are bound to this device.'))
      ->setHeaders(
        array(
          null,
          pht('Service'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Bound Services'))
      ->setTable($table);
  }


}
