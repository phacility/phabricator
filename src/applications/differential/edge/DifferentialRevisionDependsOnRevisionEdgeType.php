<?php

final class DifferentialRevisionDependsOnRevisionEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 5;

  public function getInverseEdgeConstant() {
    return DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function shouldPreventCycles() {
    return true;
  }

  public function getConduitKey() {
    return 'revision.parent';
  }

  public function getConduitName() {
    return pht('Revision Has Parent');
  }

  public function getConduitDescription() {
    return pht(
      'The source revision depends on changes in the destination revision.');
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s parent revision(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s parent revision(s): %s.',
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
      '%s edited parent revision(s), added %s: %s; removed %s: %s.',
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
      '%s added %s parent revision(s) for %s: %s.',
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
      '%s removed %s parent revision(s) for %s: %s.',
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
      '%s edited parent revision(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }
}
