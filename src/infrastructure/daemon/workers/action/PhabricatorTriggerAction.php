<?php

/**
 * A trigger action reacts to a scheduled event.
 *
 * Almost all events should use a @{class:PhabricatorScheduleTaskTriggerAction}.
 * Avoid introducing new actions without strong justification. See that class
 * for discussion of concerns.
 */
abstract class PhabricatorTriggerAction extends Phobject {

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
   * Validate action configuration.
   *
   * @param map<string, wild> Map of action properties.
   * @return void
   */
  abstract public function validateProperties(array $properties);


  /**
   * Execute this action.
   *
   * IMPORTANT: Trigger actions must execute quickly!
   *
   * In most cases, trigger actions should queue a worker task and then exit.
   * The actual trigger execution occurs in a locked section in the trigger
   * daemon and blocks all other triggers. By queueing a task instead of
   * performing processing directly, triggers can execute more involved actions
   * without blocking other triggers.
   *
   * Almost all events should use @{class:PhabricatorScheduleTaskTriggerAction}
   * to do this, ensuring that they execute quickly.
   *
   * An action may trigger a long time after it is scheduled. For example,
   * a meeting reminder may be scheduled at 9:45 AM, but the action may not
   * execute until later (for example, because the server was down for
   * maintenance). You can detect cases like this by comparing `$this_epoch`
   * (which holds the time the event was scheduled to execute at) to
   * `PhabricatorTime::getNow()` (which returns the current time). In the
   * case of a meeting reminder, you may want to ignore the action if it
   * executes too late to be useful (for example, after a meeting is over).
   *
   * Because actions should normally queue a task and there may be a second,
   * arbitrarily long delay between trigger execution and task execution, it
   * may be simplest to pass the trigger time to the task and then make the
   * decision to discard the action there.
   *
   * @param int|null Last time the event occurred, or null if it has never
   *   triggered before.
   * @param int The scheduled time for the current action. This may be
   *   significantly different from the current time.
   * @return void
   */
  abstract public function execute($last_epoch, $this_epoch);

}
