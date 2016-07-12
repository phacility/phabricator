<?php

final class PonderAnswerView extends AphrontTagView {

  private $answer;
  private $transactions;
  private $timeline;
  private $handle;

  public function setAnswer($answer) {
    $this->answer = $answer;
    return $this;
  }

  public function setTransactions($transactions) {
    $this->transactions = $transactions;
    return $this;
  }

  public function setTimeline($timeline) {
    $this->timeline = $timeline;
    return $this;
  }

  public function setHandle($handle) {
    $this->handle = $handle;
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
    $status = $answer->getStatus();
    $author_phid = $answer->getAuthorPHID();
    $actions = $this->buildAnswerActions();
    $handle = $this->handle;
    $id = $answer->getID();

    if ($status == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $answer,
        PhabricatorPolicyCapability::CAN_EDIT);

      $message = array();
      $message[] = phutil_tag(
        'em',
        array(),
        pht('This answer has been hidden.'));

      if ($can_edit) {
        $message[] = phutil_tag(
          'a',
          array(
            'href' => "/ponder/answer/edit/{$id}/",
          ),
          pht('Edit Answer'));
      }
      $message = phutil_implode_html(' ', $message);

      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->appendChild($message);
    }

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIcon('fa-bars')
      ->setDropdownMenu($actions);

    $header_name = phutil_tag(
      'a',
      array(
        'href' => $handle->getURI(),
      ),
      $handle->getName());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setEpoch($answer->getDateModified())
      ->setHeader($header_name)
      ->addActionLink($action_button)
      ->setImage($handle->getImageURI())
      ->setImageURL($handle->getURI());

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      PhabricatorMarkupEngine::renderOneObject(
        $answer,
        $answer->getMarkupField(),
        $viewer));

    $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName("A$id");

    $content_id = celerity_generate_unique_node_id();
    $footer = id(new PonderFooterView())
      ->setContentID($content_id)
      ->setCount(count($this->transactions));

    $content = phutil_tag_div(
      'ponder-answer-content', array($anchor, $content, $footer));

    $answer_view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::GREY)
      ->addClass('ponder-answer')
      ->appendChild($content);

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
        $this->timeline,
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
      ->setObject($answer);

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
