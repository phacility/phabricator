<?php

final class PonderPostBodyView extends AphrontView {

  private $target;
  private $question;
  private $handles;
  private $preview;
  private $anchorName;
  private $user;
  private $action;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('ponder-post-css');

    $user = $this->user;
    $question = $this->question;
    $target = $this->target;
    $content = $target->getContent();
    $info = array();


    $content = PhabricatorMarkupEngine::renderOneObject(
      $target,
      $target->getMarkupField(),
      $this->user);

    $content =
      '<div class="phabricator-remarkup">'.
        $content.
      '</div>';

    $author = $this->handles[$target->getAuthorPHID()];
    $actions = array($author->renderLink().' '.$this->action);
    $author_link = $author->renderLink();
    $xaction_view = id(new PhabricatorTransactionView())
      ->setUser($user)
      ->setImageURI($author->getImageURI())
      ->setContentSource($target->getContentSource())
      ->setActions($actions);

    if ($this->target instanceof PonderAnswer) {
      $xaction_view->addClass("ponder-answer");
    }
    else {
      $xaction_view->addClass("ponder-question");
    }

    if ($this->preview) {
      $xaction_view->setIsPreview($this->preview);
    } else {
      $xaction_view->setEpoch($target->getDateCreated());
      if ($this->target instanceof PonderAnswer) {
        $anchor_text = 'Q' . $question->getID(). '#A' . $target->getID();
        $xaction_view->setAnchor('A'.$target->getID(), $anchor_text);
        $xaction_view->addClass("ponder-answer");
      }
    }

    $xaction_view->appendChild(
      '<div class="ponder-post-core">'.
      $content.
      '</div>'
    );

    $outerview = $xaction_view;
    if (!$this->preview) {
      $outerview =
        id(new PonderVotableView())
        ->setPHID($target->getPHID())
        ->setCount($target->getVoteCount())
        ->setVote($target->getUserVote());

      if ($this->target instanceof PonderAnswer) {
        $outerview->setURI('/ponder/answer/vote/');
      }
      else {
        $outerview->setURI('/ponder/question/vote/');
      }

      $outerview->appendChild($xaction_view);
    }

    return $outerview->render();
  }

  private function renderHandleList(array $phids) {
    $result = array();
    foreach ($phids as $phid) {
      $result[] = $this->handles[$phid]->renderLink();
    }
    return implode(', ', $result);
  }



}
