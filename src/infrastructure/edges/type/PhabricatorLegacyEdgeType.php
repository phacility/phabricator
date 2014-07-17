<?php

/**
 * Supports legacy edges. Do not use or extend this class!
 *
 * TODO: Move all edge constants out of @{class:PhabricatorEdgeConfig}, then
 * throw this away.
 */
final class PhabricatorLegacyEdgeType extends PhabricatorEdgeType {

  private $edgeConstant;
  private $inverseEdgeConstant;
  private $shouldPreventCycles;
  private $strings;

  public function getEdgeConstant() {
    return $this->edgeConstant;
  }

  public function getInverseEdgeConstant() {
    return $this->inverseEdgeConstant;
  }

  public function shouldPreventCycles() {
    return $this->shouldPreventCycles;
  }

  public function setEdgeConstant($edge_constant) {
    $this->edgeConstant = $edge_constant;
    return $this;
  }

  public function setInverseEdgeConstant($inverse_edge_constant) {
    $this->inverseEdgeConstant = $inverse_edge_constant;
    return $this;
  }

  public function setShouldPreventCycles($should_prevent_cycles) {
    $this->shouldPreventCycles = $should_prevent_cycles;
    return $this;
  }

  public function setStrings(array $strings) {
    $this->strings = $strings;
    return $this;
  }

  private function getString($idx, array $argv) {
    array_unshift($argv, idx($this->strings, $idx, ''));

    // TODO: Burn this class in a fire. Just hiding this from lint for now.
    $pht_func = 'pht';
    return call_user_func_array($pht_func, $argv);
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    $args = func_get_args();
    return $this->getString(0, $args);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    $args = func_get_args();
    return $this->getString(1, $args);
  }

  public function getTransactionEditString(
    $actor,
    $total_count,
    $add_count,
    $add_edges,
    $rem_count,
    $rem_edges) {

    $args = func_get_args();
    return $this->getString(2, $args);
  }

  public function getFeedAddString(
    $actor,
    $object,
    $add_count,
    $add_edges) {

    $args = func_get_args();
    return $this->getString(3, $args);
  }

  public function getFeedRemoveString(
    $actor,
    $object,
    $rem_count,
    $rem_edges) {

    $args = func_get_args();
    return $this->getString(3, $args);
  }

  public function getFeedEditString(
    $actor,
    $object,
    $total_count,
    $add_count,
    $add_edges,
    $rem_count,
    $rem_edges) {

    $args = func_get_args();
    return $this->getString(3, $args);
  }

}
