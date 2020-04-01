<?php

abstract class PhabricatorConfigDatabaseController
  extends PhabricatorConfigServicesController {

  protected function renderIcon($status) {
    switch ($status) {
      case PhabricatorConfigStorageSchema::STATUS_OKAY:
        $icon = 'fa-check-circle green';
        break;
      case PhabricatorConfigStorageSchema::STATUS_WARN:
        $icon = 'fa-exclamation-circle yellow';
        break;
      case PhabricatorConfigStorageSchema::STATUS_FAIL:
      default:
        $icon = 'fa-times-circle red';
        break;
    }

    return id(new PHUIIconView())
      ->setIcon($icon);
  }

  protected function renderAttr($attr, $issue) {
    if ($issue) {
      return phutil_tag(
        'span',
        array(
          'style' => 'color: #aa0000;',
        ),
        $attr);
    } else {
      return $attr;
    }
  }

  protected function renderBoolean($value) {
    if ($value === null) {
      return '';
    } else if ($value === true) {
      return pht('Yes');
    } else {
      return pht('No');
    }
  }

}
