<?php

final class AlmanacBindingTableView extends AphrontView {

  private $bindings;
  private $handles;
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
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
    $handles = $this->getHandles();
    $viewer = $this->getUser();

    $rows = array();
    foreach ($bindings as $binding) {
      $addr = $binding->getInterface()->getAddress();
      $port = $binding->getInterface()->getPort();

      $rows[] = array(
        $binding->getID(),
        $handles[$binding->getServicePHID()]->renderLink(),
        $handles[$binding->getDevicePHID()]->renderLink(),
        $handles[$binding->getInterface()->getNetworkPHID()]->renderLink(),
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
