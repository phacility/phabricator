<?php

final class PhabricatorFeedTransactionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $createdMin;
  private $createdMax;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDateCreatedBetween($min, $max) {
    $this->createdMin = $min;
    $this->createdMax = $max;
    return $this;
  }

  protected function loadPage() {
    $queries = $this->newTransactionQueries();

    $xactions = array();

    if ($this->shouldLimitResults()) {
      $limit = $this->getRawResultLimit();
      if (!$limit) {
        $limit = null;
      }
    } else {
      $limit = null;
    }

    // We're doing a bit of manual work to get paging working, because this
    // query aggregates the results of a large number of subqueries.

    // Overall, we're ordering transactions by "<dateCreated, phid>". Ordering
    // by PHID is not very meaningful, but we don't need the ordering to be
    // especially meaningful, just consistent. Using PHIDs is easy and does
    // everything we need it to technically.

    // To actually configure paging, if we have an external cursor, we load
    // the internal cursor first. Then we pass it to each subquery and the
    // subqueries pretend they just loaded a page where it was the last object.
    // This configures their queries properly and we can aggregate a cohesive
    // set of results by combining all the queries.

    $cursor = $this->getExternalCursorString();
    if ($cursor !== null) {
      $cursor_object = $this->newInternalCursorFromExternalCursor($cursor);
    } else {
      $cursor_object = null;
    }

    $is_reversed = $this->getIsQueryOrderReversed();

    $created_min = $this->createdMin;
    $created_max = $this->createdMax;

    $xaction_phids = $this->phids;

    foreach ($queries as $query) {
      $query->withDateCreatedBetween($created_min, $created_max);

      if ($xaction_phids !== null) {
        $query->withPHIDs($xaction_phids);
      }

      if ($limit !== null) {
        $query->setLimit($limit);
      }

      if ($cursor_object !== null) {
        $query
          ->setAggregatePagingCursor($cursor_object)
          ->setIsQueryOrderReversed($is_reversed);
      }

      $query->setOrder('global');

      $query_xactions = $query->execute();
      foreach ($query_xactions as $query_xaction) {
        $xactions[] = $query_xaction;
      }

      $xactions = msortv($xactions, 'newGlobalSortVector');
      if ($is_reversed) {
        $xactions = array_reverse($xactions);
      }

      if ($limit !== null) {
        $xactions = array_slice($xactions, 0, $limit);

        // If we've found enough transactions to fill up the entire requested
        // page size, we can narrow the search window: transactions after the
        // last transaction we've found so far can't possibly be part of the
        // result set.

        if (count($xactions) === $limit) {
          $last_date = last($xactions)->getDateCreated();
          if ($is_reversed) {
            if ($created_max === null) {
              $created_max = $last_date;
            } else {
              $created_max = min($created_max, $last_date);
            }
          } else {
            if ($created_min === null) {
              $created_min = $last_date;
            } else {
              $created_min = max($created_min, $last_date);
            }
          }
        }
      }
    }

    return $xactions;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFeedApplication';
  }

  private function newTransactionQueries() {
    $viewer = $this->getViewer();

    $queries = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationTransactionQuery')
      ->execute();

    $type_map = array();

    // If we're querying for specific transaction PHIDs, we only need to
    // consider queries which may load transactions with subtypes present
    // in the list.

    // For example, if we're loading Maniphest Task transaction PHIDs, we know
    // we only have to look at Maniphest Task transactions, since other types
    // of objects will never have the right transaction PHIDs.

    $xaction_phids = $this->phids;
    if ($xaction_phids) {
      foreach ($xaction_phids as $xaction_phid) {
        $type_map[phid_get_subtype($xaction_phid)] = true;
      }
    }

    $results = array();
    foreach ($queries as $query) {
      if ($type_map) {
        $type = $query->getTemplateApplicationTransaction()
          ->getApplicationTransactionType();
        if (!isset($type_map[$type])) {
          continue;
        }
      }

      $results[] = id(clone $query)
        ->setViewer($viewer)
        ->setParentQuery($this);
    }

    return $results;
  }

  protected function newExternalCursorStringForResult($object) {
    return (string)$object->getPHID();
  }

  protected function applyExternalCursorConstraintsToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $subquery,
    $cursor) {
    $subquery->withPHIDs(array($cursor));
  }

}
