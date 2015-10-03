<?php

interface HarbormasterBuildableAdapterInterface {

  public function getHarbormasterBuildablePHID();
  public function getHarbormasterContainerPHID();
  public function getQueuedHarbormasterBuildRequests();
  public function queueHarbormasterBuildRequest(
    HarbormasterBuildRequest $request);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  HarbormasterBuildableAdapterInterface  )------------------------------ */
/*

  public function getHarbormasterBuildablePHID() {
    return $this->getObject()->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return null;
  }

  public function getQueuedHarbormasterBuildPlanPHIDs() {
    return $this->buildPlanPHIDs;
  }

  public function queueHarbormasterBuildPlanPHID($phid) {
    $this->buildPlanPHIDs[] = $phid;
  }

*/
