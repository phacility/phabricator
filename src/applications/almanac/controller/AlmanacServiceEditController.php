<?php

final class AlmanacServiceEditController
  extends AlmanacServiceController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new AlmanacServiceEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $this->requireApplicationCapability(
        AlmanacCreateServicesCapability::CAPABILITY);

      $list_uri = $this->getApplicationURI('service/');

      $service_type = $request->getStr('serviceType');
      $service_types = AlmanacServiceType::getAllServiceTypes();
      if (empty($service_types[$service_type])) {
        return $this->buildServiceTypeResponse($list_uri);
      }

      $engine
        ->addContextParameter('serviceType', $service_type)
        ->setServiceType($service_type);
    }

    return $engine->buildResponse();
  }

  private function buildServiceTypeResponse($cancel_uri) {
    $service_types = AlmanacServiceType::getAllServiceTypes();

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
      AlmanacManageClusterServicesCapability::CAPABILITY,
      pht('You have permission to create cluster services.'),
      pht('You do not have permission to create new cluster services.'));

    $type_control = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Service Type'))
      ->setName('serviceType')
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
        $service_type->getServiceTypeConstant(),
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
    $crumbs->setBorder(true);

    $title = pht('Choose Service Type');
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Create Service'))
      ->setHeaderIcon('fa-plus-square');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($type_control)
      ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Continue'))
            ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setFormErrors($errors)
      ->setHeaderText(pht('Service'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
