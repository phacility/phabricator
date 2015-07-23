<?php

abstract class AlmanacController
  extends PhabricatorController {

  protected function buildAlmanacPropertiesTable(
    AlmanacPropertyInterface $object) {

    $viewer = $this->getViewer();
    $properties = $object->getAlmanacProperties();

    $this->requireResource('almanac-css');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_DEFAULT);

    // Before reading values from the object, read defaults.
    $defaults = mpull(
      $field_list->getFields(),
      'getValueForStorage',
      'getFieldKey');

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($object);

    Javelin::initBehavior('phabricator-tooltips', array());

    $icon_builtin = id(new PHUIIconView())
      ->setIconFont('fa-circle')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Builtin Property'),
          'align' => 'E',
        ));

    $icon_custom = id(new PHUIIconView())
      ->setIconFont('fa-circle-o grey')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Custom Property'),
          'align' => 'E',
        ));

    $builtins = $object->getAlmanacPropertyFieldSpecifications();

    // Sort fields so builtin fields appear first, then fields are ordered
    // alphabetically.
    $fields = $field_list->getFields();
    $fields = msort($fields, 'getFieldKey');

    $head = array();
    $tail = array();
    foreach ($fields as $field) {
      $key = $field->getFieldKey();
      if (isset($builtins[$key])) {
        $head[$key] = $field;
      } else {
        $tail[$key] = $field;
      }
    }

    $fields = $head + $tail;

    $rows = array();
    foreach ($fields as $key => $field) {
      $value = $field->getValueForStorage();

      $is_builtin = isset($builtins[$key]);

      $delete_uri = $this->getApplicationURI('property/delete/');
      $delete_uri = id(new PhutilURI($delete_uri))
        ->setQueryParams(
          array(
            'objectPHID' => $object->getPHID(),
            'key' => $key,
          ));

      $edit_uri = $this->getApplicationURI('property/edit/');
      $edit_uri = id(new PhutilURI($edit_uri))
        ->setQueryParams(
          array(
            'objectPHID' => $object->getPHID(),
            'key' => $key,
          ));

      $delete = javelin_tag(
        'a',
        array(
          'class' => ($can_edit
            ? 'button grey small'
            : 'button grey small disabled'),
          'sigil' => 'workflow',
          'href' => $delete_uri,
        ),
        $is_builtin ? pht('Reset') : pht('Delete'));

      $default = idx($defaults, $key);
      $is_default = ($default !== null && $default === $value);

      $display_value = PhabricatorConfigJSON::prettyPrintJSON($value);
      if ($is_default) {
        $display_value = phutil_tag(
          'span',
          array(
            'class' => 'almanac-default-property-value',
          ),
          $display_value);
      }

      $display_key = $key;
      if ($can_edit) {
        $display_key = javelin_tag(
          'a',
          array(
            'href' => $edit_uri,
            'sigil' => 'workflow',
          ),
          $display_key);
      }

      $rows[] = array(
        ($is_builtin ? $icon_builtin : $icon_custom),
        $display_key,
        $display_value,
        $delete,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No properties.'))
      ->setHeaders(
        array(
          null,
          pht('Name'),
          pht('Value'),
          null,
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide',
          'action',
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
      ->setTable($table);
  }

  protected function addLockMessage(PHUIObjectBoxView $box, $message) {
    $doc_link = phutil_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink('Almanac User Guide'),
        'target' => '_blank',
      ),
      pht('Learn More'));

    $error_view = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setErrors(
        array(
          array($message, ' ', $doc_link),
        ));

    $box->setInfoView($error_view);
  }

}
