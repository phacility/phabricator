<?php

final class AlmanacBindingTableView extends AphrontView {

  private $bindings;
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function setBindings(array $bindings) {
    $this->bindings = $bindings;
    return $this;
  }

  public function getBindings() {
    return $this->bindings;
  }

  public function render() {
    $bindings = $this->getBindings();
    $viewer = $this->getUser();

    $phids = array();
    foreach ($bindings as $binding) {
      $phids[] = $binding->getServicePHID();
      $phids[] = $binding->getDevicePHID();
      $phids[] = $binding->getInterface()->getNetworkPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $rows = array();
    foreach ($bindings as $binding) {
      $addr = $binding->getInterface()->getAddress();
      $port = $binding->getInterface()->getPort();

      $rows[] = array(
        $binding->getID(),
        $handles->renderHandle($binding->getServicePHID()),
        $handles->renderHandle($binding->getDevicePHID()),
        $handles->renderHandle($binding->getInterface()->getNetworkPHID()),
        $binding->getInterface()->renderDisplayAddress(),
        phutil_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => '/almanac/binding/'.$binding->getID().'/',
          ),
          pht('Details')),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString($this->getNoDataString())
      ->setHeaders(
        array(
          pht('ID'),
          pht('Service'),
          pht('Device'),
          pht('Network'),
          pht('Interface'),
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          'wide',
          'action',
        ));

    return $table;
  }

}
