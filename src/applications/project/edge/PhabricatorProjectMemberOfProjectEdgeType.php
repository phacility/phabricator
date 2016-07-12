<?php

final class PhabricatorProjectMemberOfProjectEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 14;

  public function getInverseEdgeConstant() {
    return PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s project(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s project(s): %s.',
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
      '%s edited project(s), added %s: %s; removed %s: %s.',
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
      '%s added %s project(s) for %s: %s.',
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
      '%s removed %s project(s) for %s: %s.',
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
      '%s edited project(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }
}
