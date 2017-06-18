<?php

final class AlmanacBindingTableView extends AphrontView {

  private $bindings;
  private $noDataString;

  private $hideServiceColumn;

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

  public function setHideServiceColumn($hide_service_column) {
    $this->hideServiceColumn = $hide_service_column;
    return $this;
  }

  public function getHideServiceColumn() {
    return $this->hideServiceColumn;
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

    $icon_disabled = id(new PHUIIconView())
      ->setIcon('fa-ban')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Disabled'),
        ));

    $icon_active = id(new PHUIIconView())
      ->setIcon('fa-check')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Active'),
        ));

    $rows = array();
    foreach ($bindings as $binding) {
      $addr = $binding->getInterface()->getAddress();
      $port = $binding->getInterface()->getPort();

      $rows[] = array(
        $binding->getID(),
        ($binding->getIsDisabled() ? $icon_disabled : $icon_active),
        $handles->renderHandle($binding->getServicePHID()),
        $handles->renderHandle($binding->getDevicePHID()),
        $handles->renderHandle($binding->getInterface()->getNetworkPHID()),
        $binding->getInterface()->renderDisplayAddress(),
        phutil_tag(
          'a',
          array(
            'class' => 'small button button-grey',
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
          null,
          pht('Service'),
          pht('Device'),
          pht('Network'),
          pht('Interface'),
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          'icon',
          '',
          '',
          '',
          'wide',
          'action',
        ))
      ->setColumnVisibility(
        array(
          true,
          true,
          !$this->getHideServiceColumn(),
        ));

    return $table;
  }

}
