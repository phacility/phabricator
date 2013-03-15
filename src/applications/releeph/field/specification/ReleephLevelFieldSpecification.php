<?php

/**
 * Provides a convenient field for storing a set of levels that you can use to
 * filter requests on.
 *
 * Levels are rendered with names and descriptions in the edit UI, and are
 * automatically documented via the "arc request" interface.
 *
 * See ReleephSeverityFieldSpecification for an example.
 */
abstract class ReleephLevelFieldSpecification
  extends ReleephFieldSpecification {

  private $error;

  abstract public function getLevels();
  abstract public function getDefaultLevel();
  abstract public function getNameForLevel($level);
  abstract public function getDescriptionForLevel($level);

  /**
   * Use getCanonicalLevel() to convert old, unsupported levels to new ones.
   */
  protected function getCanonicalLevel($misc_level) {
    return $misc_level;
  }

  public function getStorageKey() {
    $class = get_class($this);
    throw new ReleephFieldSpecificationIncompleteException(
      $this,
      "You must implement getStorageKey() for children of {$class}!");
  }

  public function renderValueForHeaderView() {
    $raw_level = $this->getValue();
    $level = $this->getCanonicalLevel($raw_level);
    return $this->getNameForLevel($level);
  }

  public function renderEditControl(AphrontRequest $request) {
    $control_name = $this->getRequiredStorageKey();
    $all_levels = $this->getLevels();

    $level = $request->getStr($control_name);

    if (!$level) {
      $level = $this->getCanonicalLevel($this->getValue());
    }

    if (!$level) {
      $level = $this->getDefaultLevel();
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setLabel('Level')
      ->setName($control_name)
      ->setValue($level);

    if ($this->error) {
      $control->setError($this->error);
    } elseif ($this->getDefaultLevel()) {
      $control->setError(true);
    }

    foreach ($all_levels as $level) {
      $name = $this->getNameForLevel($level);
      $description = $this->getDescriptionForLevel($level);
      $control->addButton($level, $name, $description);
    }

    return $control;
  }

  public function renderHelpForArcanist() {
    $text = '';
    $levels = $this->getLevels();
    $default = $this->getDefaultLevel();
    foreach ($levels as $level) {
      $name = $this->getNameForLevel($level);
      $description = $this->getDescriptionForLevel($level);
      $default_marker = ' ';
      if ($level === $default) {
        $default_marker = '*';
      }
      $text .= "    {$default_marker} **{$name}**\n";
      $text .= phutil_console_wrap($description."\n", 8);
    }
    return $text;
  }

  public function validate($value) {
    if ($value === null) {
      $this->error = 'Required';
      $label = $this->getName();
      throw new ReleephFieldParseException(
        $this,
        "You must provide a {$label} level");
    }

    $levels = $this->getLevels();
    if (!in_array($value, $levels)) {
      $label = $this->getName();
      throw new ReleephFieldParseException(
        $this,
        "Level '{$value}' is not a valid {$label} level in this project.");
    }
  }

  public function setValueFromConduitAPIRequest(ConduitAPIRequest $request) {
    $key = $this->getRequiredStorageKey();
    $label = $this->getName();
    $name = idx($request->getValue('fields', array()), $key);

    if (!$name) {
      $level = $this->getDefaultLevel();
      if (!$level) {
        throw new ReleephFieldParseException(
          $this,
          "No value given for {$label}, ".
          "and no default is given for this level!");
      }
    } else {
      $level = $this->getLevelByName($name);
    }

    if (!$level) {
      throw new ReleephFieldParseException(
        $this,
        "Unknown {$label} level name '{$name}'");
    }
    $this->setValue($level);
  }

  private $nameMap = array();

  public function getLevelByName($name) {
    // Build this once
    if (!$this->nameMap) {
      foreach ($this->getLevels() as $level) {
        $level_name = $this->getNameForLevel($level);
        $this->nameMap[$level_name] = $level;
      }
    }
    return idx($this->nameMap, $name);
  }

  protected function appendSelectControls(
    AphrontFormView $form,
    AphrontRequest $request,
    array $all_releeph_requests,
    array $all_releeph_requests_without_this_field) {

    $buttons = array(null => 'All');

    // Add in known level/names
    foreach ($this->getLevels() as $level) {
      $name = $this->getNameForLevel($level);
      $buttons[$name] = $name;
    }

    // Add in any names we've seen in the wild, as well.
    foreach ($all_releeph_requests as $releeph_request) {
      $raw_level = $this->setReleephRequest($releeph_request)->getValue();
      if (!$raw_level) {
        // The ReleephRequest might not have a level set
        continue;
      }
      $level = $this->getCanonicalLevel($raw_level);
      $name = $this->getNameForLevel($level);
      $buttons[$name] = $name;
    }

    $key = $this->getRequiredStorageKey();
    $current = $request->getStr($key);

    $counters = array(null => count($all_releeph_requests_without_this_field));
    foreach ($all_releeph_requests_without_this_field as $releeph_request) {
      $raw_level = $this->setReleephRequest($releeph_request)->getValue();
      if (!$raw_level) {
        // The ReleephRequest might not have a level set
        continue;
      }
      $level = $this->getCanonicalLevel($raw_level);
      $name = $this->getNameForLevel($level);

      if (!isset($counters[$name])) {
        $counters[$name] = 0;
      }
      $counters[$name]++;
    }

    $control = id(new AphrontFormCountedToggleButtonsControl())
      ->setLabel($this->getName())
      ->setValue($current)
      ->setBaseURI($request->getRequestURI(), $key)
      ->setButtons($buttons)
      ->setCounters($counters);

    $form
      ->appendChild($control)
      ->addHiddenInput($key, $current);
  }

  protected function selectReleephRequests(AphrontRequest $request,
                                           array &$releeph_requests) {
    $key = $this->getRequiredStorageKey();
    $current = $request->getStr($key);

    if (!$current) {
      return;
    }

    $filtered = array();
    foreach ($releeph_requests as $releeph_request) {
      $raw_level = $this->setReleephRequest($releeph_request)->getValue();
      $level = $this->getCanonicalLevel($raw_level);
      $name = $this->getNameForLevel($level);
      if ($name == $current) {
        $filtered[] = $releeph_request;
      }
    }

    $releeph_requests = $filtered;
  }

}
