<?php

final class ConpherenceWidgetConfigConstants extends ConpherenceConstants {

  const UPDATE_URI = '/conpherence/update/';

  public static function getWidgetPaneBehaviorConfig() {
    return array(
      'widgetBaseUpdateURI' => self::UPDATE_URI,
      'widgetRegistry' => self::getWidgetRegistry(),
    );
  }

  public static function getWidgetRegistry() {
    return array(
      'conpherence-message-pane' => array(
        'name' => pht('Thread'),
        'icon' => 'fa-comment',
        'deviceOnly' => true,
        'hasCreate' => false,
      ),
      'widgets-people' => array(
        'name' => pht('Participants'),
        'icon' => 'fa-users',
        'deviceOnly' => false,
        'hasCreate' => true,
        'createData' => array(
          'refreshFromResponse' => true,
          'action' => ConpherenceUpdateActions::ADD_PERSON,
          'customHref' => null,
        ),
      ),
      'widgets-settings' => array(
        'name' => pht('Notifications'),
        'icon' => 'fa-wrench',
        'deviceOnly' => false,
        'hasCreate' => false,
      ),
      'widgets-edit' => array(
        'name' => pht('Edit Room'),
        'icon' => 'fa-pencil',
        'deviceOnly' => false,
        'hasCreate' => false,
      ),
    );
  }

}
