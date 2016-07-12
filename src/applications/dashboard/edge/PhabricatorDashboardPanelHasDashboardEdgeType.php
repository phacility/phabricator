<?php

final class PhabricatorDashboardPanelHasDashboardEdgeType
  extends PhabricatorEdgeType {

  const EDGECONST = 46;

  public function getInverseEdgeConstant() {
    return PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s dashboard(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s dashboard(s): %s.',
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
      '%s edited dashboard(s), added %s: %s; removed %s: %s.',
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
      '%s added %s dashboard(s) for %s: %s.',
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
      '%s removed %s dashboard(s) for %s: %s.',
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
      '%s edited dashboard(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }
}
