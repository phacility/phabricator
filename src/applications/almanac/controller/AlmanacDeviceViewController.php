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

    $phids = array();
    foreach ($interfaces as $interface) {
      $phids[] = $interface->getNetworkPHID();
      $phids[] = $interface->getDevicePHID();
    }
    $handles = $this->loadViewerHandles($phids);

    $table = id(new AlmanacInterfaceTableView())
      ->setUser($viewer)
      ->setInterfaces($interfaces)
      ->setHandles($handles);

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
      ->appendChild($table);
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
      ->appendChild($table);


  }

}
