<?php

final class PhabricatorManiphestTaskTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
    return id(new ManiphestTask())
      ->setStatus($this->generateTaskStatus())
      ->setPriority($this->generateTaskPriority())
      ->setSubPriority($this->generateTaskSubPriority())
      ->setAuthorPHID($this->loadAuthorPHID())
      ->setTitle($this->generateTitle())
      ->setDescription($this->generateDescription())
      ->setOwnerPHID($this->loadOwnerPHID())
      ->save();
  }

  private function loadPhabrictorUserPHID() {
    return $this->loadOneRandom("PhabricatorUser")->getPHID();
  }

  public function loadAuthorPHID() {
    return $this->loadPhabrictorUserPHID();
  }

  public function loadOwnerPHID() {
    if (rand(0, 3) == 0) {
      return null;
    } else {
      return $this->loadPhabrictorUserPHID();
    }
  }

  public function generateTitle() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate();
  }

  public function generateDescription() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generateSeveral(rand(30, 40));
  }

  public function generateTaskPriority() {
    return array_rand(ManiphestTaskPriority::getTaskPriorityMap());
  }

  public function generateTaskSubPriority() {
    return rand(2 << 16, 2 << 32);
  }

  public function generateTaskStatus() {
    $statuses = array_keys(ManiphestTaskStatus::getTaskStatusMap());
    // Make sure 4/5th of all generated Tasks are open
    $random = rand(0, 4);
    if ($random != 0) {
      return ManiphestTaskStatus::STATUS_OPEN;
    } else {
      return array_rand($statuses);
    }
  }


}
