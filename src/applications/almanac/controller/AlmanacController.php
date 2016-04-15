<?php

abstract class AlmanacController
  extends PhabricatorController {

  protected function buildAlmanacPropertiesTable(
    AlmanacPropertyInterface $object) {

    $viewer = $this->getViewer();
    $properties = $object->getAlmanacProperties();

    $this->requireResource('almanac-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $properties = $object->getAlmanacProperties();

    $icon_builtin = id(new PHUIIconView())
      ->setIcon('fa-circle')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Builtin Property'),
          'align' => 'E',
        ));

    $icon_custom = id(new PHUIIconView())
      ->setIcon('fa-circle-o grey')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Custom Property'),
          'align' => 'E',
        ));

    $builtins = $object->getAlmanacPropertyFieldSpecifications();
    $defaults = mpull($builtins, null, 'getValueForTransaction');

    // Sort fields so builtin fields appear first, then fields are ordered
    // alphabetically.
    $properties = msort($properties, 'getFieldName');

    $head = array();
    $tail = array();
    foreach ($properties as $property) {
      $key = $property->getFieldName();
      if (isset($builtins[$key])) {
        $head[$key] = $property;
      } else {
        $tail[$key] = $property;
      }
    }

    $properties = $head + $tail;

    $delete_base = $this->getApplicationURI('property/delete/');
    $edit_base = $this->getApplicationURI('property/update/');

    $rows = array();
    foreach ($properties as $key => $property) {
      $value = $property->getFieldValue();

      $is_builtin = isset($builtins[$key]);

      $delete_uri = id(new PhutilURI($delete_base))
        ->setQueryParams(
          array(
            'key' => $key,
            'objectPHID' => $object->getPHID(),
          ));

      $edit_uri = id(new PhutilURI($edit_base))
        ->setQueryParams(
          array(
            'key' => $key,
            'objectPHID' => $object->getPHID(),
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
    $add_uri = id(new PhutilURI($edit_base))
      ->setQueryParam('objectPHID', $object->getPHID());

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
      ->setIcon('fa-plus');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Properties'))
      ->addActionLink($add_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

  protected function addClusterMessage(
    $positive,
    $negative) {

    $can_manage = $this->hasApplicationCapability(
      AlmanacManageClusterServicesCapability::CAPABILITY);

    $doc_link = phutil_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'Clustering Introduction'),
        'target' => '_blank',
      ),
      pht('Learn More'));

    if ($can_manage) {
      $severity = PHUIInfoView::SEVERITY_NOTICE;
      $message = $positive;
    } else {
      $severity = PHUIInfoView::SEVERITY_WARNING;
      $message = $negative;
    }

    $icon = id(new PHUIIconView())
      ->setIcon('fa-sitemap');

    return id(new PHUIInfoView())
      ->setSeverity($severity)
      ->setErrors(
        array(
          array($icon, ' ', $message, ' ', $doc_link),
        ));

  }

  protected function getPropertyDeleteURI($object) {
    return null;
  }

  protected function getPropertyUpdateURI($object) {
    return null;
  }

}
