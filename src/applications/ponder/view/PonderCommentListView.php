<?php

final class PonderCommentListView extends AphrontView {
  private $handles;
  private $comments;
  private $target;
  private $actionURI;
  private $questionID;

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

      $comment_anchor = '';
      $comment_markup[] = hsprintf(
        '<tr>'.
          '<th><a name="comment-%s" /></th>'.
          '<td>'.
            '<div class="phabricator-remarkup ponder-comment-markup">'.
              '%s&nbsp;&mdash;%s&nbsp;<span class="ponder-datestamp">%s</span>'.
            '</div>'.
          '</td>'.
        '</tr>',
        $comment->getID(),
        $body,
        $handle->renderLink(),
        phabricator_datetime($comment->getDateCreated(), $user));
    }

    $addview = id(new PonderAddCommentView)
      ->setTarget($this->target)
      ->setUser($user)
      ->setQuestionID($this->questionID)
      ->setActionURI($this->actionURI);

    $comment_markup[] = hsprintf(
      '<tr><th>&nbsp;</th><td>%s</td></tr>',
      $addview->render());

    $comment_markup = phutil_tag(
      'table',
      array(
        'class' => 'ponder-comments',
      ),
      $comment_markup);

    return $comment_markup;
  }

}
