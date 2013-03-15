<?php

final class ReleephRequestIntentsView extends AphrontView {

  private $releephRequest;
  private $releephProject;

  public function setReleephRequest(ReleephRequest $rq) {
    $this->releephRequest = $rq;
    return $this;
  }

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function render() {
    require_celerity_resource('releeph-intents');

    return phutil_tag(
      'div',
      array(
        'class' => 'releeph-intents',
      ),
      array(
        $this->renderIntentList(ReleephRequest::INTENT_WANT),
        $this->renderIntentList(ReleephRequest::INTENT_PASS)
      ));
  }

  private function renderIntentList($render_intent) {
    if (!$this->releephProject) {
      throw new Exception("Must call setReleephProject() first!");
    }

    $project = $this->releephProject;
    $request = $this->releephRequest;
    $handles = $request->getHandles();

    $is_want = $render_intent == ReleephRequest::INTENT_WANT;
    $should = $request->shouldBeInBranch();

    $pusher_links = array();
    $user_links = array();

    $intents = $request->getUserIntents();
    foreach ($intents as $user_phid => $user_intent) {
      if ($user_intent == $render_intent) {
        $is_pusher = $project->isPusherPHID($user_phid);

        if ($is_pusher) {
          $pusher_links[] = phutil_tag(
            'span',
            array(
              'class' => 'pusher'
            ),
            $handles[$user_phid]->renderLink());
        } else {
          $class = 'bystander';
          if ($request->getRequestUserPHID() == $user_phid) {
            $class = 'requestor';
          }
          $user_links[] = phutil_tag(
            'span',
            array(
              'class' => $class,
            ),
            $handles[$user_phid]->renderLink());
        }
      }
    }

    // Don't render anything
    if (!$pusher_links && !$user_links) {
      return null;
    }

    $links = array_merge($pusher_links, $user_links);
    if ($links) {
      $markup = $links;
    } else {
      $markup = array('&nbsp;');
    }

    // Stick an arrow up front
    $arrow_class = 'arrow '.$render_intent;
    array_unshift($markup, phutil_tag(
      'div',
      array(
        'class' => $arrow_class,
      ),
      ''));

    return phutil_tag(
      'div',
      array(
        'class' => 'intents',
      ),
      $markup);
  }


}
