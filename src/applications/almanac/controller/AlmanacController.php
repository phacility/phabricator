<?php

abstract class AlmanacController
  extends PhabricatorController {

  protected function buildAlmanacPropertiesTable(
    AlmanacPropertyInterface $object) {

    $viewer = $this->getViewer();
    $properties = $object->getAlmanacProperties();

    $rows = array();
    foreach ($properties as $property) {
      $value = $property->getFieldValue();

      $rows[] = array(
        $property->getFieldName(),
        PhabricatorConfigJSON::prettyPrintJSON($value),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No properties.'))
      ->setHeaders(
        array(
          pht('Name'),
          pht('Value'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide',
        ));

    $phid = $object->getPHID();
    $add_uri = $this->getApplicationURI("property/edit/?objectPHID={$phid}");

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $add_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($add_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit)
      ->setText(pht('Add Property'))
      ->setIcon(
        id(new PHUIIconView())
          ->setIconFont('fa-plus'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Properties'))
      ->addActionLink($add_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);
  }

}
