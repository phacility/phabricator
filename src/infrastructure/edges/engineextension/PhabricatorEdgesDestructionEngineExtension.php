<?php

final class PhabricatorEdgesDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'edges';

  public function getExtensionName() {
    return pht('Edges');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $src_phid = $object->getPHID();

    try {
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($src_phid))
        ->execute();
    } catch (Exception $ex) {
      // This is (presumably) a "no edges for this PHID type" exception.
      return;
    }

    $editor = new PhabricatorEdgeEditor();
    foreach ($edges as $type => $type_edges) {
      foreach ($type_edges as $src => $src_edges) {
        foreach ($src_edges as $dst => $edge) {
          $editor->removeEdge($edge['src'], $edge['type'], $edge['dst']);
        }
      }
    }
    $editor->save();
  }

}
