<?php

final class MultimeterControl {

  private static $instance;

  private $events = array();
  private $sampleRate;
  private $pauseDepth;

  private $eventViewer;
  private $eventContext;

  private function __construct() {
    // Private.
  }

  public static function newInstance() {
    $instance = new MultimeterControl();

    // NOTE: We don't set the sample rate yet. This allows the multimeter to
    // be initialized and begin recording events, then make a decision about
    // whether the page will be sampled or not later on (once we've loaded
    // enough configuration).

    self::$instance = $instance;
    return self::getInstance();
  }

  public static function getInstance() {
    return self::$instance;
  }

  public function isActive() {
    return ($this->sampleRate !== 0) && ($this->pauseDepth == 0);
  }

  public function setSampleRate($rate) {
    if ($rate && (mt_rand(1, $rate) == $rate)) {
      $sample_rate = $rate;
    } else {
      $sample_rate = 0;
    }

    $this->sampleRate = $sample_rate;

    return;
  }

  public function pauseMultimeter() {
    $this->pauseDepth++;
    return $this;
  }

  public function unpauseMultimeter() {
    if (!$this->pauseDepth) {
      throw new Exception(pht('Trying to unpause an active multimeter!'));
    }
    $this->pauseDepth--;
    return $this;
  }


  public function newEvent($type, $label, $cost) {
    if (!$this->isActive()) {
      return null;
    }

    $event = id(new MultimeterEvent())
      ->setEventType($type)
      ->setEventLabel($label)
      ->setResourceCost($cost)
      ->setEpoch(PhabricatorTime::getNow());

    $this->events[] = $event;

    return $event;
  }

  public function saveEvents() {
    if (!$this->isActive()) {
      return;
    }

    $events = $this->events;
    if (!$events) {
      return;
    }

    if ($this->sampleRate === null) {
      throw new Exception(pht('Call setSampleRate() before saving events!'));
    }

    // Don't sample any of this stuff.
    $this->pauseMultimeter();

    $use_scope = AphrontWriteGuard::isGuardActive();
    if ($use_scope) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    } else {
      AphrontWriteGuard::allowDangerousUnguardedWrites(true);
    }

    $caught = null;
    try {
      $this->writeEvents();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    if ($use_scope) {
      unset($unguarded);
    } else {
      AphrontWriteGuard::allowDangerousUnguardedWrites(false);
    }

    $this->unpauseMultimeter();

    if ($caught) {
      throw $caught;
    }
  }

  private function writeEvents() {
    $events = $this->events;

    $random = Filesystem::readRandomBytes(32);
    $request_key = PhabricatorHash::digestForIndex($random);

    $host_id = $this->loadHostID(php_uname('n'));
    $context_id = $this->loadEventContextID($this->eventContext);
    $viewer_id = $this->loadEventViewerID($this->eventViewer);
    $label_map = $this->loadEventLabelIDs(mpull($events, 'getEventLabel'));

    foreach ($events as $event) {
      $event
        ->setRequestKey($request_key)
        ->setSampleRate($this->sampleRate)
        ->setEventHostID($host_id)
        ->setEventContextID($context_id)
        ->setEventViewerID($viewer_id)
        ->setEventLabelID($label_map[$event->getEventLabel()])
        ->save();
    }
  }

  public function setEventContext($event_context) {
    $this->eventContext = $event_context;
    return $this;
  }

  public function getEventContext() {
    return $this->eventContext;
  }

  public function setEventViewer($viewer) {
    $this->eventViewer = $viewer;
    return $this;
  }

  private function loadHostID($host) {
    $map = $this->loadDimensionMap(new MultimeterHost(), array($host));
    return idx($map, $host);
  }

  private function loadEventViewerID($viewer) {
    $map = $this->loadDimensionMap(new MultimeterViewer(), array($viewer));
    return idx($map, $viewer);
  }

  private function loadEventContextID($context) {
    $map = $this->loadDimensionMap(new MultimeterContext(), array($context));
    return idx($map, $context);
  }

  private function loadEventLabelIDs(array $labels) {
    return $this->loadDimensionMap(new MultimeterLabel(), $labels);
  }

  private function loadDimensionMap(MultimeterDimension $table, array $names) {
    $hashes = array();
    foreach ($names as $name) {
      $hashes[] = PhabricatorHash::digestForIndex($name);
    }

    $objects = $table->loadAllWhere('nameHash IN (%Ls)', $hashes);
    $map = mpull($objects, 'getID', 'getName');

    $need = array();
    foreach ($names as $name) {
      if (isset($map[$name])) {
        continue;
      }
      $need[] = $name;
    }

    foreach ($need as $name) {
      $object = id(clone $table)
        ->setName($name)
        ->save();
      $map[$name] = $object->getID();
    }

    return $map;
  }

}
