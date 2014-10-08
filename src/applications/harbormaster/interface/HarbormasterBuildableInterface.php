<?php

interface HarbormasterBuildableInterface {

  public function getHarbormasterBuildablePHID();
  public function getHarbormasterContainerPHID();

  public function getBuildVariables();

  public function getAvailableBuildVariables();

}
