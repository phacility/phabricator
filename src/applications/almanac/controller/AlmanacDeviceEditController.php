<?php

final class AlmanacDeviceEditController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $list_uri = $this->getApplicationURI('device/');

    $id = $request->getURIData('id');
    if ($id) {
      $device = id(new AlmanacDeviceQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$device) {
        return new Aphront404Response();
      }

      $is_new = false;
      $device_uri = $device->getURI();
      $cancel_uri = $device_uri;
      $title = pht('Edit Device');
      $save_button = pht('Save Changes');
    } else {
      $this->requireApplicationCapability(
        AlmanacCreateDevicesCapability::CAPABILITY);

      $device = AlmanacDevice::initializeNewDevice();
      $is_new = true;

      $cancel_uri = $list_uri;
      $title = pht('Create Device');
      $save_button = pht('Create Device');
    }

    $v_name = $device->getName();
    $e_name = true;
    $validation_exception = null;

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $device->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

      $type_name = AlmanacDeviceTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new AlmanacDeviceTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new AlmanacDeviceTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new AlmanacDeviceTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new AlmanacDeviceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new AlmanacDeviceEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($device, $xactions);

        $device_uri = $device->getURI();
        return id(new AphrontRedirectResponse())->setURI($device_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);

        $device->setViewPolicy($v_view);
        $device->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($device)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($device)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($device)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($save_button));

    $box = id(new PHUIObjectBoxView())
      ->setValidationException($validation_exception)
      ->setHeaderText($title)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Device'));
    } else {
      $crumbs->addTextCrumb($device->getName(), $device_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
