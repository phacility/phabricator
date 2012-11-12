<?php

final class PhabricatorTimelineIterator implements Iterator {

  protected $cursorName;
  protected $eventTypes;

  protected $cursor;

  protected $index  = -1;
  protected $events = array();

  const LOAD_CHUNK_SIZE = 128;

  public function __construct($cursor_name, array $event_types) {
    $this->cursorName = $cursor_name;
    $this->eventTypes = $event_types;
  }

  protected function loadEvents() {
    if (!$this->cursor) {
      $this->cursor = id(new PhabricatorTimelineCursor())->loadOneWhere(
        'name = %s',
        $this->cursorName);
      if (!$this->cursor) {
        $cursor = new PhabricatorTimelineCursor();
        $cursor->setName($this->cursorName);
        $cursor->setPosition(0);
        $cursor->save();

        $this->cursor = $cursor;
      }
    }

    $event = new PhabricatorTimelineEvent('NULL');
    $event_data = new PhabricatorTimelineEventData();
    $raw_data = queryfx_all(
      $event->establishConnection('r'),
      'SELECT event.*, event_data.eventData eventData
        FROM %T event
        LEFT JOIN %T event_data ON event_data.id = event.dataID
        WHERE event.id > %d AND event.type in (%Ls)
        ORDER BY event.id ASC LIMIT %d',
      $event->getTableName(),
      $event_data->getTableName(),
      $this->cursor->getPosition(),
      $this->eventTypes,
      self::LOAD_CHUNK_SIZE);

    $events = $event->loadAllFromArray($raw_data);
    $events = mpull($events, null, 'getID');
    $raw_data = ipull($raw_data, 'eventData', 'id');
    foreach ($raw_data as $id => $data) {
      if ($data) {
        $decoded = json_decode($data, true);
        $events[$id]->setData($decoded);
      }
    }

    $this->events = $events;

    if ($this->events) {
      $this->events = array_values($this->events);
      $this->index = 0;
    } else {
      $this->cursor = null;
    }
  }

  public function current() {
    return $this->events[$this->index];
  }

  public function key() {
    return $this->events[$this->index]->getID();
  }

  public function next() {
    if ($this->valid()) {
      $this->cursor->setPosition($this->key());
      $this->cursor->save();
    }

    $this->index++;
    if (!$this->valid()) {
      $this->loadEvents();
    }
  }

  public function valid() {
    return isset($this->events[$this->index]);
  }

  public function rewind() {
    if (!$this->valid()) {
      $this->loadEvents();
    }
  }

}
