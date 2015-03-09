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
      'widgets-files' => array(
        'name' => pht('Files'),
        'icon' => 'fa-files-o',
        'deviceOnly' => false,
        'hasCreate' => false,
      ),
      'widgets-calendar' => array(
        'name' => pht('Calendar'),
        'icon' => 'fa-calendar',
        'deviceOnly' => false,
        'hasCreate' => true,
        'createData' => array(
          'refreshFromResponse' => false,
          'action' => ConpherenceUpdateActions::ADD_STATUS,
          'customHref' => '/calendar/event/create/',
        ),
      ),
      'widgets-settings' => array(
        'name' => pht('Settings'),
        'icon' => 'fa-wrench',
        'deviceOnly' => false,
        'hasCreate' => false,
      ),
    );
  }

}
