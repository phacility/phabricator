<?php

/**
 * Defines an edge type.
 *
 * Edges are typed, directed connections between two objects. They are used to
 * represent most simple relationships, like when a user is subscribed to an
 * object or an object is a member of a project.
 *
 * @task load   Loading Types
 */
abstract class PhabricatorEdgeType extends Phobject {

  final public function getEdgeConstant() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('EDGECONST');
    if ($const === false) {
      throw new Exception(
        pht(
          '%s class "%s" must define an %s property.',
          __CLASS__,
          get_class($this),
          'EDGECONST'));
    }

    if (!is_int($const) || ($const <= 0)) {
      throw new Exception(
        pht(
          '%s class "%s" has an invalid %s property. '.
          'Edge constants must be positive integers.',
          __CLASS__,
          get_class($this),
          'EDGECONST'));
    }

    return $const;
  }

  public function getInverseEdgeConstant() {
    return null;
  }

  public function shouldPreventCycles() {
    return false;
  }

  public function shouldWriteInverseTransactions() {
    return false;
  }

  public function getTransactionPreviewString($actor) {
    return pht(
      '%s edited edge metadata.',
      $actor);
  }

  public function getTransactionAddString(
    $actor,
    $add_count,
    $add_edges) {

    return pht(
      '%s added %s edge(s): %s.',
      $actor,
      $add_count,
      $add_edges);
  }

  public function getTransactionRemoveString(
    $actor,
    $rem_count,
    $rem_edges) {

    return pht(
      '%s removed %s edge(s): %s.',
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
      '%s edited %s edge(s), added %s: %s; removed %s: %s.',
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
      '%s added %s edge(s) to %s: %s.',
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
      '%s removed %s edge(s) from %s: %s.',
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
      '%s edited %s edge(s) for %s, added %s: %s; removed %s: %s.',
      $actor,
      $total_count,
      $object,
      $add_count,
      $add_edges,
      $rem_count,
      $rem_edges);
  }


/* -(  Loading Types  )------------------------------------------------------ */


  /**
   * @task load
   */
  public static function getAllTypes() {
    static $type_map;

    if ($type_map === null) {
      $types = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();

      foreach ($types as $class => $type) {
        $const = $type->getEdgeConstant();

        if (isset($map[$const])) {
          throw new Exception(
            pht(
              'Two edge types ("%s", "%s") share the same edge constant '.
              '(%d). Each edge type must have a unique constant.',
              $class,
              get_class($map[$const]),
              $const));
        }

        $map[$const] = $type;
      }

      // Check that all the inverse edge definitions actually make sense. If
      // edge type A says B is its inverse, B must exist and say that A is its
      // inverse.

      foreach ($map as $const => $type) {
        $inverse = $type->getInverseEdgeConstant();
        if ($inverse === null) {
          continue;
        }

        if (empty($map[$inverse])) {
          throw new Exception(
            pht(
              'Edge type "%s" ("%d") defines an inverse type ("%d") which '.
              'does not exist.',
              get_class($type),
              $const,
              $inverse));
        }

        $inverse_inverse = $map[$inverse]->getInverseEdgeConstant();
        if ($inverse_inverse !== $const) {
          throw new Exception(
            pht(
              'Edge type "%s" ("%d") defines an inverse type ("%d"), but that '.
              'inverse type defines a different type ("%d") as its '.
              'inverse.',
              get_class($type),
              $const,
              $inverse,
              $inverse_inverse));
        }
      }

      $type_map = $map;
    }

    return $type_map;
  }


  /**
   * @task load
   */
  public static function getByConstant($const) {
    $type = idx(self::getAllTypes(), $const);

    if (!$type) {
      throw new Exception(
        pht('Unknown edge constant "%s"!', $const));
    }

    return $type;
  }

}
