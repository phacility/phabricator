<?php

final class CelerityManagementMapWorkflow
  extends CelerityManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('map')
      ->setExamples('**map** [options]')
      ->setSynopsis(pht('Rebuild static resource maps.'))
      ->setArguments(
        array());
  }

  public function execute(PhutilArgumentParser $args) {
    $resources_map = CelerityResources::getAll();

    foreach ($resources_map as $name => $resources) {
      $this->rebuildResources($resources);
    }

    return 0;
  }

  /**
   * Rebuild the resource map for a resource source.
   *
   * @param CelerityResources Resource source to rebuild.
   * @return void
   */
  private function rebuildResources(CelerityResources $resources) {
    $binary_map = $this->rebuildBinaryResources($resources);

    $xformer = id(new CelerityResourceTransformer())
      ->setMinify(false)
      ->setRawURIMap(ipull($binary_map, 'uri'));

    $text_map = $this->rebuildTextResources($resources, $xformer);

    $resource_graph = array();
    $requires_map = array();
    $provides_map = array();
    foreach ($text_map as $name => $info) {
      if (isset($info['provides'])) {
        $provides_map[$info['provides']] = $info['hash'];

        // We only need to check for cycles and add this to the requires map
        // if it actually requires anything.
        if (!empty($info['requires'])) {
          $resource_graph[$info['provides']] = $info['requires'];
          $requires_map[$info['hash']] = $info['requires'];
        }
      }
    }

    $this->detectGraphCycles($resource_graph);

    $hash_map = ipull($binary_map, 'hash') + ipull($text_map, 'hash');


    // TODO: Actually do things.

    var_dump($provides_map);
    var_dump($requires_map);
    var_dump($hash_map);
  }


  /**
   * Find binary resources (like PNG and SWF) and return information about
   * them.
   *
   * @param CelerityResources Resource map to find binary resources for.
   * @return map<string, map<string, string>> Resource information map.
   */
  private function rebuildBinaryResources(CelerityResources $resources) {
    $binary_map = $resources->findBinaryResources();

    $result_map = array();
    foreach ($binary_map as $name => $data_hash) {
      $hash = $resources->getCelerityHash($data_hash.$name);

      $result_map[$name] = array(
        'hash' => $hash,
        'uri' => $resources->getResourceURI($hash, $name),
      );
    }

    return $result_map;
  }


  /**
   * Find text resources (like JS and CSS) and return information about them.
   *
   * @param CelerityResources Resource map to find text resources for.
   * @param CelerityResourceTransformer Configured resource transformer.
   * @return map<string, map<string, string>> Resource information map.
   */
  private function rebuildTextResources(
    CelerityResources $resources,
    CelerityResourceTransformer $xformer) {

    $text_map = $resources->findTextResources();

    $result_map = array();
    foreach ($text_map as $name => $data_hash) {
      $raw_data = $resources->getResourceData($name);
      $xformed_data = $xformer->transformResource($name, $raw_data);

      $data_hash = $resources->getCelerityHash($xformed_data);
      $hash = $resources->getCelerityHash($data_hash.$name);

      list($provides, $requires) = $this->getProvidesAndRequires(
        $name,
        $raw_data);

      $result_map[$name] = array(
        'hash' => $hash,
      );

      if ($provides !== null) {
        $result_map[$name] += array(
          'provides' => $provides,
          'requires' => $requires,
        );
      }
    }

    return $result_map;
  }


  /**
   * Parse the `@provides` and `@requires` symbols out of a text resource, like
   * JS or CSS.
   *
   * @param string Resource name.
   * @param string Resource data.
   * @return pair<string|null, list<string>|nul> The `@provides` symbol and the
   *    list of `@requires` symbols. If the resource is not part of the
   *    dependency graph, both are null.
   */
  private function getProvidesAndRequires($name, $data) {
    $parser = new PhutilDocblockParser();

    $matches = array();
    $ok = preg_match('@/[*][*].*?[*]/@s', $data, $matches);
    if (!$ok) {
      throw new Exception(
        pht(
          'Resource "%s" does not have a header doc comment. Encode '.
          'dependency data in a header docblock.',
          $name));
    }

    list($description, $metadata) = $parser->parse($matches[0]);

    $provides = preg_split('/\s+/', trim(idx($metadata, 'provides')));
    $requires = preg_split('/\s+/', trim(idx($metadata, 'requires')));
    $provides = array_filter($provides);
    $requires = array_filter($requires);

    if (!$provides) {
      // Tests and documentation-only JS is permitted to @provide no targets.
      return array(null, null);
    }

    if (count($provides) > 1) {
      throw new Exception(
        pht(
          'Resource "%s" must @provide at most one Celerity target.',
          $name));
    }

    return array(head($provides), $requires);
  }


  /**
   * Check for dependency cycles in the resource graph. Raises an exception if
   * a cycle is detected.
   *
   * @param map<string, list<string>> Map of `@provides` symbols to their
   *                                  `@requires` symbols.
   * @return void
   */
  private function detectGraphCycles(array $nodes) {
    $graph = id(new CelerityResourceGraph())
      ->addNodes($nodes)
      ->setResourceGraph($nodes)
      ->loadGraph();

    foreach ($nodes as $provides => $requires) {
      $cycle = $graph->detectCycles($provides);
      if ($cycle) {
        throw new Exception(
          pht(
            'Cycle detected in resource graph: %s',
            implode(' > ', $cycle)));
      }
    }
  }

}
