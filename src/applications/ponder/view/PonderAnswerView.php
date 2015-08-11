<?php

final class PonderAnswerView extends AphrontTagView {

  private $answer;
  private $transactions;
  private $engine;

  public function setAnswer($answer) {
    $this->answer = $answer;
    return $this;
  }

  public function setTransactions($transactions) {
    $this->transactions = $transactions;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'ponder-answer-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('ponder-view-css');
    $answer = $this->answer;
    $viewer = $this->getUser();
    $author_phid = $answer->getAuthorPHID();
    $actions = $this->buildAnswerActions();

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIconFont('fa-bars')
      ->setDropdownMenu($actions);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setEpoch($answer->getDateCreated())
      ->setHeader($viewer->renderHandle($author_phid))
      ->addActionLink($action_button);

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup mlt mlb msr msl',
      ),
      PhabricatorMarkupEngine::renderOneObject(
        $answer,
        $answer->getMarkupField(),
        $viewer));

    $id = $answer->getID();
    $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName("A$id");

    $content_id = celerity_generate_unique_node_id();
    $footer = id(new PonderFooterView())
      ->setContentID($content_id)
      ->setCount(count($this->transactions));

    $votes = $answer->getVoteCount();
    if ($votes) {
      $icon = id(new PHUIIconView())
        ->setIconFont('fa-thumbs-up');
      $helpful = phutil_tag(
        'span',
        array(
          'class' => 'ponder-footer-action',
        ),
        array($votes, $icon));
      $footer->addAction($helpful);
    }

    $answer_view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($anchor)
      ->appendChild($content)
      ->appendChild($footer);

    $transaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($answer->getPHID())
      ->setTransactions($this->transactions)
      ->setMarkupEngine($this->engine);

    $comment_view = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($answer->getPHID())
      ->setShowPreview(false)
      ->setHeaderText(pht('Answer Comment'))
      ->setAction("/ponder/answer/comment/{$id}/")
      ->setSubmitButtonName(pht('Comment'));

    $hidden_view = phutil_tag(
      'div',
      array(
        'id' => $content_id,
        'style' => 'display: none;',
      ),
      array(
        $transaction_view,
        $comment_view,
      ));

    return array(
      $answer_view,
      $hidden_view,
    );
  }

  private function buildAnswerActions() {
    $viewer = $this->getUser();
    $answer = $this->answer;
    $id = $answer->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $answer,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($answer)
      ->setObjectURI('Q'.$answer->getQuestionID());

    $user_marked = $answer->getUserVote();
    $can_vote = $viewer->isLoggedIn();

    if ($user_marked) {
      $helpful_uri = "/ponder/answer/helpful/remove/{$id}/";
      $helpful_icon = 'fa-times';
      $helpful_text = pht('Remove Helpful');
    } else {
      $helpful_uri = "/ponder/answer/helpful/add/{$id}/";
      $helpful_icon = 'fa-thumbs-up';
      $helpful_text = pht('Mark as Helpful');
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon($helpful_icon)
        ->setName($helpful_text)
        ->setHref($helpful_uri)
        ->setRenderAsForm(true)
        ->setDisabled(!$can_vote)
        ->setWorkflow($can_vote));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Answer'))
        ->setHref("/ponder/answer/edit/{$id}/")
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-list')
        ->setName(pht('View History'))
        ->setHref("/ponder/answer/history/{$id}/"));

    return $view;
  }
}
