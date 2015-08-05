<?php

abstract class DifferentialHarbormasterField
  extends DifferentialCustomField {

  abstract protected function getDiffPropertyKeys();
  abstract protected function loadHarbormasterTargetMessages(
    array $target_phids);
  abstract protected function getLegacyProperty();
  abstract protected function newModernMessage(array $message);
  abstract protected function renderHarbormasterStatus(
    DifferentialDiff $diff,
    array $messages);
  abstract protected function newHarbormasterMessageView(array $messages);

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    // TODO: This load is slightly inefficient, but most of this is moving
    // to Harbormaster and this simplifies the transition. Eat 1-2 extra
    // queries for now.
    $keys = $this->getDiffPropertyKeys();

    $properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d AND name IN (%Ls)',
      $diff->getID(),
      $keys);
    $properties = mpull($properties, 'getData', 'getName');

    foreach ($keys as $key) {
      $diff->attachProperty($key, idx($properties, $key));
    }

    $messages = array();

    $buildable = $diff->getBuildable();
    if ($buildable) {
      $target_phids = array();
      foreach ($buildable->getBuilds() as $build) {
        foreach ($build->getBuildTargets() as $target) {
          $target_phids[] = $target->getPHID();
        }
      }

      if ($target_phids) {
        $messages = $this->loadHarbormasterTargetMessages($target_phids);
      }
    }

    if (!$messages) {
      // No Harbormaster messages, so look for legacy messages and make them
      // look like modern messages.
      $legacy_messages = $diff->getProperty($this->getLegacyProperty());
      if ($legacy_messages) {
        // Show the top 100 legacy lint messages. Previously, we showed some
        // by default and let the user toggle the rest. With modern messages,
        // we can send the user to the Harbormaster detail page. Just show
        // "a lot" of messages in legacy cases to try to strike a balance
        // between implementation simplicitly and compatibility.
        $legacy_messages = array_slice($legacy_messages, 0, 100);

        foreach ($legacy_messages as $message) {
          try {
            $modern = $this->newModernMessage($message);
            $messages[] = $modern;
          } catch (Exception $ex) {
            // Ignore any poorly formatted messages.
          }
        }
      }
    }

    $status = $this->renderHarbormasterStatus($diff, $messages);

    if ($messages) {
      $path_map = mpull($diff->loadChangesets(), 'getID', 'getFilename');
      foreach ($path_map as $path => $id) {
        $href = '#C'.$id.'NL';

        // TODO: When the diff is not the right-hand-size diff, we should
        // ideally adjust this URI to be absolute.

        $path_map[$path] = $href;
      }

      $view = $this->newHarbormasterMessageView($messages);
      if ($view) {
        $view->setPathURIMap($path_map);
      }
    } else {
      $view = null;
    }

    if ($view) {
      $view = phutil_tag(
        'div',
        array(
          'class' => 'differential-harbormaster-table-view',
        ),
        $view);
    }

    return array(
      $status,
      $view,
    );
  }

}
