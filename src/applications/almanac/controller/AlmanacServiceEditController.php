<?php

final class AlmanacServiceEditController
  extends AlmanacServiceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $list_uri = $this->getApplicationURI('service/');

    $id = $request->getURIData('id');
    if ($id) {
      $service = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$service) {
        return new Aphront404Response();
      }

      $is_new = false;
      $service_uri = $service->getURI();
      $cancel_uri = $service_uri;
      $title = pht('Edit Service');
      $save_button = pht('Save Changes');
    } else {
      $cancel_uri = $list_uri;

      $this->requireApplicationCapability(
        AlmanacCreateServicesCapability::CAPABILITY);

      $service_class = $request->getStr('serviceClass');
      $service_types = AlmanacServiceType::getAllServiceTypes();
      if (empty($service_types[$service_class])) {
        return $this->buildServiceTypeResponse($service_types, $cancel_uri);
      }

      $service_type = $service_types[$service_class];
      if ($service_type->isClusterServiceType()) {
        $this->requireApplicationCapability(
          AlmanacCreateClusterServicesCapability::CAPABILITY);
      }

      $service = AlmanacService::initializeNewService();
      $service->setServiceClass($service_class);
      $service->attachServiceType($service_type);
      $is_new = true;

      $title = pht('Create Service');
      $save_button = pht('Create Service');
    }

    $v_name = $service->getName();
    $e_name = true;
    $validation_exception = null;

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $service->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    if ($request->isFormPost() && $request->getStr('edit')) {
      $v_name = $request->getStr('name');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

      $type_name = AlmanacServiceTransaction::TYPE_NAME;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new AlmanacServiceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new AlmanacServiceEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($service, $xactions);

        $service_uri = $service->getURI();
        return id(new AphrontRedirectResponse())->setURI($service_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_name = $ex->getShortMessage($type_name);

        $service->setViewPolicy($v_view);
        $service->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($service)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('edit', true)
      ->addHiddenInput('serviceClass', $service->getServiceClass())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($service)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($service)
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
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Service'));
    } else {
      $crumbs->addTextCrumb($service->getName(), $service_uri);
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

  private function buildServiceTypeResponse(array $service_types, $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $e_service = null;
    $errors = array();
    if ($request->isFormPost()) {
      $e_service = pht('Required');
      $errors[] = pht(
        'To create a new service, you must select a service type.');
    }

    list($can_cluster, $cluster_link) = $this->explainApplicationCapability(
      AlmanacCreateClusterServicesCapability::CAPABILITY,
      pht('You have permission to create cluster services.'),
      pht('You do not have permission to create new cluster services.'));


    $type_control = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Service Type'))
      ->setName('serviceClass')
      ->setError($e_service);

    foreach ($service_types as $service_type) {
      $is_cluster = $service_type->isClusterServiceType();
      $is_disabled = ($is_cluster && !$can_cluster);

      if ($is_cluster) {
        $extra = $cluster_link;
      } else {
        $extra = null;
      }

      $type_control->addButton(
        get_class($service_type),
        $service_type->getServiceTypeName(),
        array(
          $service_type->getServiceTypeDescription(),
          $extra,
        ),
        $is_disabled ? 'disabled' : null,
        $is_disabled);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Create Service'));

    $title = pht('Choose Service Type');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($type_control)
      ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Continue'))
            ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setFormErrors($errors)
      ->setHeaderText($title)
      ->appendChild($form);

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
