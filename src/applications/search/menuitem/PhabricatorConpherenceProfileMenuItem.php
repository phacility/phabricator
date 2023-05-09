<?php

final class PhabricatorConpherenceProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'conpherence';
  const FIELD_CONPHERENCE = 'conpherence';

  private $conpherence;

  public function getMenuItemTypeIcon() {
    return 'fa-comments';
  }

  public function getMenuItemTypeName() {
    return pht('Conpherence');
  }

  public function canAddToObject($object) {
    $application = new PhabricatorConpherenceApplication();

    if (!$application->isInstalled()) {
      return false;
    }

    return true;
  }

  public function attachConpherence($conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }

  public function getConpherence() {
    $conpherence = $this->conpherence;

    if (!$conpherence) {
      return null;
    }

    return $conpherence;
  }

  public function willGetMenuItemViewList(array $items) {
    $viewer = $this->getViewer();
    $room_phids = array();
    foreach ($items as $item) {
      $room_phids[] = $item->getMenuItemProperty('conpherence');
    }

    $rooms = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withPHIDs($room_phids)
      ->needProfileImage(true)
      ->execute();

    $rooms = mpull($rooms, null, 'getPHID');
    foreach ($items as $item) {
      $room_phid = $item->getMenuItemProperty('conpherence');
      $room = idx($rooms, $room_phid, null);
      $item->getMenuItem()->attachConpherence($room);
    }
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $room = $this->getConpherence($config);
    if (!$room) {
      return pht('(Restricted/Invalid Conpherence)');
    }

    $default = $room->getTitle();
    return $this->getNameFromConfig($config, $default);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey(self::FIELD_CONPHERENCE)
        ->setLabel(pht('Conpherence Room'))
        ->setDatasource(new ConpherenceThreadDatasource())
        ->setIsRequired(true)
        ->setSingleValue($config->getMenuItemProperty('conpherence')),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getNameFromConfig($config)),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $room = $this->getConpherence($config);
    if (!$room) {
      return array();
    }

    $participants = $room->getParticipants();
    $viewer_phid = $viewer->getPHID();
    $unread_count = null;
    if (isset($participants[$viewer_phid])) {
      $data = $room->getDisplayData($viewer);
      $unread_count = $data['unread_count'];
    }

    $item = $this->newItemView()
      ->setURI('/'.$room->getMonogram())
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-comments');

    if ($unread_count) {
      $item->newCount($unread_count);
    }

    return array(
      $item,
    );
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {

    $viewer = $this->getViewer();
    $errors = array();

    if ($field_key == self::FIELD_CONPHERENCE) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose a room.'),
         $field_key);
      }

      foreach ($xactions as $xaction) {
        $new = $xaction['new'];

        if (!$new) {
          continue;
        }

        if ($new === $value) {
          continue;
        }

        $rooms = id(new ConpherenceThreadQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($new))
          ->execute();
        if (!$rooms) {
          $errors[] = $this->newInvalidError(
            pht(
              'Room "%s" is not a valid room which you have '.
              'permission to see.',
              $new),
            $xaction['xaction']);
        }
      }
    }

    return $errors;
  }

}
