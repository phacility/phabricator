<?php

final class PholioMockHasTaskEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 37;

  public function getInverseEdgeConstant() {
    return ManiphestTaskHasMockEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s task(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s task(s): %s.',
      $actor,
      $rem_count,
      $rem_edges);
  }

  public function getTransactionEditString(
    $actor,
    $total_count,
    $add_count,
    $add_edges,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s edited task(s), added %s: %s; removed %s: %s.',
      $actor,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }

  public function getFeedAddString(
    $actor,
    $object,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s task(s) for %s: %s.',
      $actor,
      $add_count,
      $object,
      $add_edges);
  }

  public function getFeedRemoveString(
    $actor,
    $object,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s task(s) for %s: %s.',
      $actor,
      $rem_count,
      $object,
      $rem_edges);
  }

  public function getFeedEditString(
    $actor,
    $object,
    $total_count,
    $add_count,
    $add_edges,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s edited task(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }
}
