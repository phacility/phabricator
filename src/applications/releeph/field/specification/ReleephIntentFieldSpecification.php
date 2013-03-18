<?php

final class ReleephIntentFieldSpecification
  extends ReleephFieldSpecification {

  public function getName() {
    return 'Intent';
  }

  public function renderValueForHeaderView() {
    return id(new ReleephRequestIntentsView())
      ->setReleephRequest($this->getReleephRequest())
      ->setReleephProject($this->getReleephProject())
      ->render();
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnRevertMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return "Approved By";
  }

  public function renderLabelForRevertMessage() {
    return "Rejected By";
  }

  public function renderValueForCommitMessage() {
    return $this->renderIntentsForCommitMessage(ReleephRequest::INTENT_WANT);
  }

  public function renderValueForRevertMessage() {
    return $this->renderIntentsForCommitMessage(ReleephRequest::INTENT_PASS);
  }

  private function renderIntentsForCommitMessage($print_intent) {
    $intents = $this->getReleephRequest()->getUserIntents();

    $requestor = $this->getReleephRequest()->getRequestUserPHID();
    $pusher_phids = $this->getReleephProject()->getPushers();

    $phids = array_unique($pusher_phids + array_keys($intents));
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->getUser())
      ->loadHandles();

    $tokens = array();
    foreach ($phids as $phid) {
      $intent = idx($intents, $phid);
      if ($intent == $print_intent) {
        $name = $handles[$phid]->getName();
        $is_pusher = in_array($phid, $pusher_phids);
        $is_requestor = $phid == $requestor;

        if ($is_pusher) {
          if ($is_requestor) {
            $token = "{$name} (pusher and requestor)";
          } else {
            $token = "{$name} (pusher)";
          }
        } else {
          if ($is_requestor) {
            $token = "{$name} (requestor)";
          } else {
            $token = $name;
          }
        }

        $tokens[] = $token;
      }
    }

    return implode(', ', $tokens);
  }

}
