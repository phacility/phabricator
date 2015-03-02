<?php

final class AlmanacQueryDevicesConduitAPIMethod
  extends AlmanacConduitAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.querydevices';
  }

  public function getMethodDescription() {
    return pht('Query Almanac devices.');
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'names' => 'optional list<phid>',
    ) + self::getPagerParamTypes();
  }

  public function defineReturnType() {
    return 'list<wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new AlmanacDeviceQuery())
      ->setViewer($viewer);

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

    $pager = $this->newPager($request);

    $devices = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($devices as $device) {
      $data[] = $this->getDeviceDictionary($device);
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }

}
