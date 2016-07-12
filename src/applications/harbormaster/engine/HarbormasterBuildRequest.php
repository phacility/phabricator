<?php

/**
 * Structure used to ask Harbormaster to start a build.
 *
 * Requests to start builds sometimes originate several layers away from where
 * they are processed. For example, Herald rules which start builds pass the
 * requests through the adapter and then through the editor before they reach
 * Harbormaster.
 *
 * This class is just a thin wrapper around these requests so we can make them
 * more complex later without needing to rewrite any APIs.
 */
final class HarbormasterBuildRequest extends Phobject {

  private $buildPlanPHID;
  private $initiatorPHID;
  private $buildParameters = array();

  public function setBuildPlanPHID($build_plan_phid) {
    $this->buildPlanPHID = $build_plan_phid;
    return $this;
  }

  public function getBuildPlanPHID() {
    return $this->buildPlanPHID;
  }

  public function setBuildParameters(array $build_parameters) {
    $this->buildParameters = $build_parameters;
    return $this;
  }

  public function getBuildParameters() {
    return $this->buildParameters;
  }

  public function setInitiatorPHID($phid) {
    $this->initiatorPHID = $phid;
    return $this;
  }

  public function getInitiatorPHID() {
    return $this->initiatorPHID;
  }

}
