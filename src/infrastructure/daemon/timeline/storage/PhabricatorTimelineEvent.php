<?php

final class PhabricatorTimelineEvent extends PhabricatorTimelineDAO {

  protected $type;
  protected $dataID;

  private $data;

  public function __construct($type, $data = null) {
    parent::__construct();

    if (strlen($type) !== 4) {
      throw new Exception("Event types must be exactly 4 characters long.");
    }

    $this->type = $type;
    $this->data = $data;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function recordEvent() {
    if ($this->getID()) {
      throw new Exception("Event has already been recorded!");
    }

    // Save the data first and point to it from the event to avoid a race
    // condition where we insert the event before the data and a consumer reads
    // it immediately.
    if ($this->data !== null) {
      $data = new PhabricatorTimelineEventData();
      $data->setEventData($this->data);
      $data->save();

      $this->setDataID($data->getID());
    }

    $this->save();
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

}
