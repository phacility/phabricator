<?php

final class AlmanacQueryServicesConduitAPIMethod
  extends AlmanacConduitAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.queryservices';
  }

  public function getMethodDescription() {
    return pht('Query Almanac services.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'names' => 'optional list<phid>',
      'devicePHIDs' => 'optional list<phid>',
      'serviceClasses' => 'optional list<string>',
    ) + self::getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'list<wild>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new AlmanacServiceQuery())
      ->setViewer($viewer)
      ->needBindings(true);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $names = $request->getValue('names');
    if ($names !== null) {
      $query->withNames($names);
    }

    $classes = $request->getValue('serviceClasses');
    if ($classes !== null) {
      $query->withServiceClasses($classes);
    }

    $device_phids = $request->getValue('devicePHIDs');
    if ($device_phids !== null) {
      $query->withDevicePHIDs($device_phids);
    }

    $pager = $this->newPager($request);

    $services = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($services as $service) {
      $phid = $service->getPHID();

      $service_bindings = $service->getBindings();
      $service_bindings = array_values($service_bindings);
      foreach ($service_bindings as $key => $service_binding) {
        $service_bindings[$key] = $this->getBindingDictionary($service_binding);
      }

      $data[] = $this->getServiceDictionary($service) + array(
        'bindings' => $service_bindings,
      );
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }

}
