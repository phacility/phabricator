<?php

final class AlmanacInterfaceTableView extends AphrontView {

  private $interfaces;
  private $handles;

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setInterfaces(array $interfaces) {
    $this->interfaces = $interfaces;
    return $this;
  }

  public function getInterfaces() {
    return $this->interfaces;
  }

  public function render() {
    $interfaces = $this->getInterfaces();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

    $rows = array();
    foreach ($interfaces as $interface) {
      $rows[] = array(
        $interface->getID(),
        $handles[$interface->getNetworkPHID()]->renderLink(),
        $interface->getAddress(),
        $interface->getPort(),
        phutil_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => '/almanac/interface/edit/'.$interface->getID().'/',
          ),
          pht('Edit')),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Network'),
          pht('Address'),
          pht('Port'),
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
          '',
          '',
          'action',
        ));

    return $table;
  }

}
