<?php

final class TransactionSearchConduitAPIMethod
  extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'transaction.search';
  }

  public function getMethodDescription() {
    return pht('Read transactions for an object.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('This method is new and experimental.');
  }

  protected function defineParamTypes() {
    return array(
      'objectIdentifier' => 'phid|string',
    ) + $this->getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $pager = $this->newPager($request);

    $object_name = $request->getValue('objectIdentifier', null);
    if (!strlen($object_name)) {
      throw new Exception(
        pht(
          'When calling "transaction.search", you must provide an object to '.
          'retrieve transactions for.'));
    }

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($object_name))
      ->executeOne();
    if (!$object) {
      throw new Exception(
        pht(
          'No object "%s" exists.',
          $object_name));
    }

    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      throw new Exception(
        pht(
          'Object "%s" does not implement "%s", so transactions can not '.
          'be loaded for it.'));
    }

    $xaction_query = PhabricatorApplicationTransactionQuery::newQueryForObject(
      $object);

    $xactions = $xaction_query
      ->withObjectPHIDs(array($object->getPHID()))
      ->setViewer($viewer)
      ->executeWithCursorPager($pager);

    if ($xactions) {
      $template = head($xactions)->getApplicationTransactionCommentObject();

      $query = new PhabricatorApplicationTransactionTemplatedCommentQuery();

      $comment_map = $query
        ->setViewer($viewer)
        ->setTemplate($template)
        ->withTransactionPHIDs(mpull($xactions, 'getPHID'))
        ->execute();

      $comment_map = msort($comment_map, 'getCommentVersion');
      $comment_map = array_reverse($comment_map);
      $comment_map = mgroup($comment_map, 'getTransactionPHID');
    } else {
      $comment_map = array();
    }

    $data = array();
    foreach ($xactions as $xaction) {
      $comments = idx($comment_map, $xaction->getPHID());

      $comment_data = array();
      if ($comments) {
        $removed = head($comments)->getIsDeleted();

        foreach ($comments as $comment) {
          if ($removed) {
            // If the most recent version of the comment has been removed,
            // don't show the history. This is for consistency with the web
            // UI, which also prevents users from retrieving the content of
            // removed comments.
            $content = array(
              'raw' => '',
            );
          } else {
            $content = array(
              'raw' => (string)$comment->getContent(),
            );
          }

          $comment_data[] = array(
            'id' => (int)$comment->getID(),
            'phid' => (string)$comment->getPHID(),
            'version' => (int)$comment->getCommentVersion(),
            'authorPHID' => (string)$comment->getAuthorPHID(),
            'dateCreated' => (int)$comment->getDateCreated(),
            'dateModified' => (int)$comment->getDateModified(),
            'removed' => (bool)$comment->getIsDeleted(),
            'content' => $content,
          );
        }
      }

      $fields = array();

      if (!$fields) {
        $fields = (object)$fields;
      }

      $data[] = array(
        'id' => (int)$xaction->getID(),
        'phid' => (string)$xaction->getPHID(),
        'authorPHID' => (string)$xaction->getAuthorPHID(),
        'objectPHID' => (string)$xaction->getObjectPHID(),
        'dateCreated' => (int)$xaction->getDateCreated(),
        'dateModified' => (int)$xaction->getDateModified(),
        'comments' => $comment_data,
        'fields' => $fields,
      );
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }
}
