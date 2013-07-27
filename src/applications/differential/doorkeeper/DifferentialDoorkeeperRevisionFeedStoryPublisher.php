<?php

final class DifferentialDoorkeeperRevisionFeedStoryPublisher
  extends DoorkeeperFeedStoryPublisher {

  public function canPublishStory(PhabricatorFeedStory $story, $object) {
    if (!($object instanceof DifferentialRevision)) {
      return false;
    }

    // Don't publish the "create" story, since pushing the object into Asana
    // naturally generates a notification which effectively serves the same
    // purpose as the "create" story.

    $action = $story->getStoryData()->getValue('action');
    switch ($action) {
      case DifferentialAction::ACTION_CREATE:
        return false;
      default:
        break;
    }

    return true;
  }

  public function willPublishStory($object) {
    return id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($object->getID()))
      ->needRelationships(true)
      ->executeOne();
  }

  public function getOwnerPHID($object) {
    return $object->getAuthorPHID();
  }

  public function getActiveUserPHIDs($object) {
    $status = $object->getStatus();
    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      return $object->getReviewers();
    } else {
      return array();
    }
  }

  public function getPassiveUserPHIDs($object) {
    $status = $object->getStatus();
    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      return array();
    } else {
      return $object->getReviewers();
    }
  }

  public function getCCUserPHIDs($object) {
    return $object->getCCPHIDs();
  }

  public function getObjectTitle($object) {
    $prefix = $this->getTitlePrefix($object);

    $lines = new PhutilNumber($object->getLineCount());
    $lines = pht('[Request, %d lines]', $lines);

    $id = $object->getID();

    $title = $object->getTitle();

    return ltrim("{$prefix} {$lines} D{$id}: {$title}");
  }

  public function getObjectURI($object) {
    return PhabricatorEnv::getProductionURI('/D'.$object->getID());
  }

  public function getObjectDescription($object) {
    return $object->getSummary();
  }

  public function isObjectClosed($object) {
    switch ($object->getStatus()) {
      case ArcanistDifferentialRevisionStatus::CLOSED:
      case ArcanistDifferentialRevisionStatus::ABANDONED:
        return true;
      default:
        return false;
    }
  }

  public function getResponsibilityTitle($object) {
    $prefix = $this->getTitlePrefix($object);
    return pht('%s Review Request', $prefix);
  }

  public function getStoryText($object) {
    $story = $this->getFeedStory();
    if ($story instanceof PhabricatorFeedStoryDifferential) {
      $text = $story->renderForAsanaBridge();
    } else {
      $text = $story->renderText();
    }
    return $text;
  }

  private function getTitlePrefix(DifferentialRevision $revision) {
    $prefix_key = 'metamta.differential.subject-prefix';
    return PhabricatorEnv::getEnvConfig($prefix_key);
  }

}
