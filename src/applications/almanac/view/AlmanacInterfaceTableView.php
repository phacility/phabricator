<?php

final class AlmanacInterfaceTableView extends AphrontView {

  private $interfaces;
  private $canEdit;

  public function setInterfaces(array $interfaces) {
    $this->interfaces = $interfaces;
    return $this;
  }

  public function getInterfaces() {
    return $this->interfaces;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function getCanEdit() {
    return $this->canEdit;
  }

  public function render() {
    $interfaces = $this->getInterfaces();
    $viewer = $this->getUser();

    $can_edit = $this->getCanEdit();

    if ($can_edit) {
      $button_class = 'small button button-grey';
    } else {
      $button_class = 'small button button-grey disabled';
    }

    $handles = $viewer->loadHandles(mpull($interfaces, 'getNetworkPHID'));

    $rows = array();
    foreach ($interfaces as $interface) {
      $rows[] = array(
        $interface->getID(),
        $handles->renderHandle($interface->getNetworkPHID()),
        $interface->getAddress(),
        $interface->getPort(),
        javelin_tag(
          'a',
          array(
            'class' => $button_class,
            'href' => '/almanac/interface/edit/'.$interface->getID().'/',
            'sigil' => ($can_edit ? null : 'workflow'),
          ),
          pht('Edit')),
        javelin_tag(
          'a',
          array(
            'class' => $button_class,
            'href' => '/almanac/interface/delete/'.$interface->getID().'/',
            'sigil' => 'workflow',
          ),
          pht('Delete')),
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
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
          '',
          '',
          'action',
          'action',
        ));

    return $table;
  }

}
