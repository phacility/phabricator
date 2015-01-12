<?php

final class PonderVotingUserHasAnswerEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 20;

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getInverseEdgeConstant() {
    return PonderAnswerHasVotingUserEdgeType::EDGECONST;
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s answer(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s answer(s): %s.',
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
      '%s edited %s answer(s), added %s: %s; removed %s: %s.',
      $actor,
      $total_count,
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
      '%s added %s answer(s) to %s: %s.',
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
      '%s removed %s answer(s) from %s: %s.',
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
      '%s edited %s answer(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $total_count,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }

}
