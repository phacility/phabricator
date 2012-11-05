<?php

final class PonderCommentListView extends AphrontView {
  private $user;
  private $handles;
  private $comments;
  private $target;
  private $actionURI;
  private $questionID;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setComments(array $comments) {
    assert_instances_of($comments, 'PonderComment');
    $this->comments = $comments;
    return $this;
  }

  public function setQuestionID($id) {
    $this->questionID = $id;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('ponder-comment-table-css');

    $user = $this->user;
    $handles = $this->handles;
    $comments = $this->comments;

    $comment_markup = array();

    foreach ($comments as $comment) {
      $handle = $handles[$comment->getAuthorPHID()];
      $body = PhabricatorMarkupEngine::renderOneObject(
        $comment,
        $comment->getMarkupField(),
        $this->user);

      $comment_anchor = '<a name="comment-' . $comment->getID() . '" />';
      $comment_markup[] =
        '<tr>'.
          '<th>'.
            $comment_anchor.
          '</th>'.
          '<td>'.
            '<div class="phabricator-remarkup ponder-comment-markup">'.
              $body.
              '&nbsp;&mdash;'.
              $handle->renderLink().
              '&nbsp;'.
              '<span class="ponder-datestamp">'.
                phabricator_datetime($comment->getDateCreated(), $user).
              '</span>'.
            '</div>'.
          '</td>'.
        '</tr>';
    }

    $addview = id(new PonderAddCommentView)
      ->setTarget($this->target)
      ->setUser($user)
      ->setQuestionID($this->questionID)
      ->setActionURI($this->actionURI);

    $comment_markup[] =
      '<tr>'.
       '<th>&nbsp;</th>'.
       '<td>'.$addview->render().'</td>'.
      '</tr>';

    $comment_markup = phutil_render_tag(
      'table',
      array(
        'class' => 'ponder-comments',
      ),
      implode("\n", $comment_markup)
    );


    return $comment_markup;
  }

}
