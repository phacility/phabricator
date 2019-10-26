<?php

final class TransactionSearchConduitAPIMethod
  extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'transaction.search';
  }

  public function getMethodDescription() {
    return pht('Read transactions and comments for an object.');
  }

  public function getMethodDocumentation() {
    $markup = pht(<<<EOREMARKUP
When an object (like a task) is edited, Phabricator creates a "transaction"
and applies it. This list of transactions on each object is the basis for
essentially all edits and comments in Phabricator. Reviewing the transaction
record allows you to see who edited an object, when, and how their edit changed
things.

One common reason to call this method is that you're implmenting a webhook and
just received a notification that an object has changed. See the Webhooks
documentation for more detailed discussion of this use case.

Constraints
===========

These constraints are supported:

  - `phids` //Optional list<phid>.// Find specific transactions by PHID. This
    is most likely to be useful if you're responding to a webhook notification
    and want to inspect only the related events.
  - `authorPHIDs` //Optional list<phid>.// Find transactions with particular
    authors.

Transaction Format
==================

Each transaction has custom data describing what the transaction did. The
format varies from transaction to transaction. The easiest way to figure out
exactly what a particular transaction looks like is to make the associated kind
of edit to a test object, then query that object.

Not all transactions have data: by default, transactions have a `null` "type"
and no additional data. This API does not expose raw transaction data because
some of it is internal, oddly named, misspelled, confusing, not useful, or
could create security or policy problems to expose directly.

New transactions are exposed (with correctly spelled, comprehensible types and
useful, reasonable fields) as we become aware of use cases for them.

EOREMARKUP
      );

    $markup = $this->newRemarkupDocumentationView($markup);

    return id(new PHUIObjectBoxView())
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeaderText(pht('Method Details'))
      ->appendChild($markup);
  }

  protected function defineParamTypes() {
    return array(
      'objectIdentifier' => 'phid|string',
      'constraints' => 'map<string, wild>',
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
          'Object "%s" (of type "%s") does not implement "%s", so '.
          'transactions can not be loaded for it.',
          $object_name,
          get_class($object),
          'PhabricatorApplicationTransactionInterface'));
    }

    $xaction_query = PhabricatorApplicationTransactionQuery::newQueryForObject(
      $object);

    $xaction_query
      ->needHandles(false)
      ->withObjectPHIDs(array($object->getPHID()))
      ->setViewer($viewer);

    $constraints = $request->getValue('constraints', array());

    $xaction_query = $this->applyConstraints($constraints, $xaction_query);

    $xactions = $xaction_query->executeWithCursorPager($pager);

    $comment_map = array();
    if ($xactions) {
      $template = head($xactions)->getApplicationTransactionCommentObject();
      if ($template) {

        $query = new PhabricatorApplicationTransactionTemplatedCommentQuery();

        $comment_map = $query
          ->setViewer($viewer)
          ->setTemplate($template)
          ->withTransactionPHIDs(mpull($xactions, 'getPHID'))
          ->execute();

        $comment_map = msort($comment_map, 'getCommentVersion');
        $comment_map = array_reverse($comment_map);
        $comment_map = mgroup($comment_map, 'getTransactionPHID');
      }
    }

    $modular_classes = array();
    $modular_objects = array();
    $modular_xactions = array();
    foreach ($xactions as $xaction) {
      if (!$xaction instanceof PhabricatorModularTransaction) {
        continue;
      }

      // TODO: Hack things so certain transactions which don't have a modular
      // type yet can use a pseudotype until they modularize. Some day, we'll
      // modularize everything and remove this.
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_INLINE:
          $modular_template = new DifferentialRevisionInlineTransaction();
          break;
        default:
          $modular_template = $xaction->getModularType();
          break;
      }

      $modular_class = get_class($modular_template);
      if (!isset($modular_objects[$modular_class])) {
        try {
          $modular_object = newv($modular_class, array());
          $modular_objects[$modular_class] = $modular_object;
        } catch (Exception $ex) {
          continue;
        }
      }

      $modular_classes[$xaction->getPHID()] = $modular_class;
      $modular_xactions[$modular_class][] = $xaction;
    }

    $modular_data_map = array();
    foreach ($modular_objects as $class => $modular_type) {
      $modular_data_map[$class] = $modular_type
        ->setViewer($viewer)
        ->loadTransactionTypeConduitData($modular_xactions[$class]);
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
      $type = null;

      if (isset($modular_classes[$xaction->getPHID()])) {
        $modular_class = $modular_classes[$xaction->getPHID()];
        $modular_object = $modular_objects[$modular_class];
        $modular_data = $modular_data_map[$modular_class];

        $type = $modular_object->getTransactionTypeForConduit($xaction);
        $fields = $modular_object->getFieldValuesForConduit(
          $xaction,
          $modular_data);
      }

      if (!$fields) {
        $fields = (object)$fields;
      }

      // If we haven't found a modular type, fallback for some simple core
      // types. Ideally, we'll modularize everything some day.
      if ($type === null) {
        switch ($xaction->getTransactionType()) {
          case PhabricatorTransactions::TYPE_COMMENT:
            $type = 'comment';
            break;
          case PhabricatorTransactions::TYPE_CREATE:
            $type = 'create';
            break;
          case PhabricatorTransactions::TYPE_EDGE:
            switch ($xaction->getMetadataValue('edge:type')) {
              case PhabricatorProjectObjectHasProjectEdgeType::EDGECONST:
                $type = 'projects';
                $fields = $this->newEdgeTransactionFields($xaction);
                break;
            }
            break;
          case PhabricatorTransactions::TYPE_SUBSCRIBERS:
            $type = 'subscribers';
            $fields = $this->newEdgeTransactionFields($xaction);
            break;
        }
      }

      $group_id = $xaction->getTransactionGroupID();
      if (!strlen($group_id)) {
        $group_id = null;
      } else {
        $group_id = (string)$group_id;
      }

      $data[] = array(
        'id' => (int)$xaction->getID(),
        'phid' => (string)$xaction->getPHID(),
        'type' => $type,
        'authorPHID' => (string)$xaction->getAuthorPHID(),
        'objectPHID' => (string)$xaction->getObjectPHID(),
        'dateCreated' => (int)$xaction->getDateCreated(),
        'dateModified' => (int)$xaction->getDateModified(),
        'groupID' => $group_id,
        'comments' => $comment_data,
        'fields' => $fields,
      );
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }

  private function applyConstraints(
    array $constraints,
    PhabricatorApplicationTransactionQuery $query) {

    PhutilTypeSpec::checkMap(
      $constraints,
      array(
        'phids' => 'optional list<string>',
        'authorPHIDs' => 'optional list<string>',
      ));

    $with_phids = idx($constraints, 'phids');

    if ($with_phids === array()) {
      throw new Exception(
        pht(
          'Constraint "phids" to "transaction.search" requires nonempty list, '.
          'empty list provided.'));
    }

    if ($with_phids) {
      $query->withPHIDs($with_phids);
    }

    $with_authors = idx($constraints, 'authorPHIDs');
    if ($with_authors === array()) {
      throw new Exception(
        pht(
          'Constraint "authorPHIDs" to "transaction.search" requires '.
          'nonempty list, empty list provided.'));
    }

    if ($with_authors) {
      $query->withAuthorPHIDs($with_authors);
    }

    return $query;
  }

  private function newEdgeTransactionFields(
    PhabricatorApplicationTransaction $xaction) {

    $record = PhabricatorEdgeChangeRecord::newFromTransaction($xaction);

    $operations = array();
    foreach ($record->getAddedPHIDs() as $phid) {
      $operations[] = array(
        'operation' => 'add',
        'phid' => $phid,
      );
    }

    foreach ($record->getRemovedPHIDs() as $phid) {
      $operations[] = array(
        'operation' => 'remove',
        'phid' => $phid,
      );
    }

    return array(
      'operations' => $operations,
    );
  }

}
