<?php

/**
 * A trigger clock implements scheduling rules for an event.
 *
 * Two examples of triggered events are a subscription which bills on the 12th
 * of every month, or a meeting reminder which sends an email 15 minutes before
 * an event. A trigger clock contains the logic to figure out exactly when
 * those times are.
 *
 * For example, it might schedule an event every hour, or every Thursday, or on
 * the 15th of every month at 3PM, or only at a specific time.
 */
abstract class PhabricatorTriggerClock extends Phobject {

  private $properties;

  public function __construct(array $properties) {
    $this->validateProperties($properties);
    $this->properties = $properties;
  }

  public function getProperties() {
    return $this->properties;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }


  /**
   * Validate clock configuration.
   *
   * @param map<string, wild> Map of clock properties.
   * @return void
   */
  abstract public function validateProperties(array $properties);


  /**
   * Get the next occurrence of this event.
   *
   * This method takes two parameters: the last time this event occurred (or
   * null if it has never triggered before) and a flag distinguishing between
   * a normal reschedule (after a successful trigger) or an update because of
   * a trigger change.
   *
   * If this event does not occur again, return `null` to stop it from being
   * rescheduled. For example, a meeting reminder may be sent only once before
   * the meeting.
   *
   * If this event does occur again, return the epoch timestamp of the next
   * occurrence.
   *
   * When performing routine reschedules, the event must move forward in time:
   * any timestamp you return must be later than the last event. For instance,
   * if this event triggers an invoice, the next invoice date must be after
   * the previous invoice date. This prevents an event from looping more than
   * once per second.
   *
   * In contrast, after an update (not a routine reschedule), the next event
   * may be scheduled at any time. For example, if a meeting is moved from next
   * week to 3 minutes from now, the clock may reschedule the notification to
   * occur 12 minutes ago. This will cause it to execute immediately.
   *
   * @param int|null Last time the event occurred, or null if it has never
   *   triggered before.
   * @param bool True if this is a reschedule after a successful trigger.
   * @return int|null Next event, or null to decline to reschedule.
   */
  abstract public function getNextEventEpoch($last_epoch, $is_reschedule);

}
