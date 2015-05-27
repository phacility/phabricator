<?php

final class ReleephIntentFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'intent';
  }

  public function getName() {
    return 'Intent';
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $pull = $this->getReleephRequest();
    $intents = $pull->getUserIntents();
    return array_keys($intents);
  }

  public function renderPropertyViewValue(array $handles) {
    $pull = $this->getReleephRequest();

    $intents = $pull->getUserIntents();
    $product = $this->getReleephProject();

    if (!$intents) {
      return null;
    }

    $pushers = array();
    $others = array();

    foreach ($intents as $phid => $intent) {
      if ($product->isAuthoritativePHID($phid)) {
        $pushers[$phid] = $intent;
      } else {
        $others[$phid] = $intent;
      }
    }

    $intents = $pushers + $others;

    $view = id(new PHUIStatusListView());
    foreach ($intents as $phid => $intent) {
      switch ($intent) {
        case ReleephRequest::INTENT_WANT:
          $icon = PHUIStatusItemView::ICON_ACCEPT;
          $color = 'green';
          $label = pht('Want');
          break;
        case ReleephRequest::INTENT_PASS:
          $icon = PHUIStatusItemView::ICON_REJECT;
          $color = 'red';
          $label = pht('Pass');
          break;
        default:
          $icon = PHUIStatusItemView::ICON_QUESTION;
          $color = 'bluegrey';
          $label = pht('Unknown Intent (%s)', $intent);
          break;
      }

      $target = $handles[$phid]->renderLink();
      if ($product->isAuthoritativePHID($phid)) {
        $target = phutil_tag('strong', array(), $target);
      }

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($icon, $color, $label)
          ->setTarget($target));
    }

    return $view;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnRevertMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return pht('Approved By');
  }

  public function renderLabelForRevertMessage() {
    return pht('Rejected By');
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
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs($phids)
      ->execute();

    $tokens = array();
    foreach ($phids as $phid) {
      $intent = idx($intents, $phid);
      if ($intent == $print_intent) {
        $name = $handles[$phid]->getName();
        $is_pusher = in_array($phid, $pusher_phids);
        $is_requestor = $phid == $requestor;

        if ($is_pusher) {
          if ($is_requestor) {
            $token = pht('%s (pusher and requestor)', $name);
          } else {
            $token = "{$name} (pusher)";
          }
        } else {
          if ($is_requestor) {
            $token = pht('%s (requestor)', $name);
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
