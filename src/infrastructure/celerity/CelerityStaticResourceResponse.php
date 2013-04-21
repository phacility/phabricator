<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_celerity_resource}, and then builds appropriate HTML or
 * Ajax responses.
 *
 * @group celerity
 */
final class CelerityStaticResourceResponse {

  private $symbols = array();
  private $needsResolve = true;
  private $resolved;
  private $packaged;
  private $metadata = array();
  private $metadataBlock = 0;
  private $behaviors = array();
  private $hasRendered = array();

  public function __construct() {
    if (isset($_REQUEST['__metablock__'])) {
      $this->metadataBlock = (int)$_REQUEST['__metablock__'];
    }
  }

  public function addMetadata($metadata) {
    $id = count($this->metadata);
    $this->metadata[$id] = $metadata;
    return $this->metadataBlock.'_'.$id;
  }

  public function getMetadataBlock() {
    return $this->metadataBlock;
  }

  /**
   * Register a behavior for initialization. NOTE: if $config is empty,
   * a behavior will execute only once even if it is initialized multiple times.
   * If $config is nonempty, the behavior will be invoked once for each config.
   */
  public function initBehavior($behavior, array $config = array()) {
    $this->requireResource('javelin-behavior-'.$behavior);

    if (empty($this->behaviors[$behavior])) {
      $this->behaviors[$behavior] = array();
    }

    if ($config) {
      $this->behaviors[$behavior][] = $config;
    }

    return $this;
  }

  public function requireResource($symbol) {
    $this->symbols[$symbol] = true;
    $this->needsResolve = true;
    return $this;
  }

  private function resolveResources() {
    if ($this->needsResolve) {
      $map = CelerityResourceMap::getInstance();
      $this->resolved = $map->resolveResources(array_keys($this->symbols));
      $this->packaged = $map->packageResources($this->resolved);
      $this->needsResolve = false;
    }
    return $this;
  }

  public function renderSingleResource($symbol) {
    $map = CelerityResourceMap::getInstance();
    $resolved = $map->resolveResources(array($symbol));
    $packaged = $map->packageResources($resolved);
    return $this->renderPackagedResources($packaged);
  }

  public function renderResourcesOfType($type) {
    $this->resolveResources();

    $resources = array();
    foreach ($this->packaged as $resource) {
      if ($resource['type'] == $type) {
        $resources[] = $resource;
      }
    }

    return $this->renderPackagedResources($resources);
  }

  private function renderPackagedResources(array $resources) {
    $output = array();
    foreach ($resources as $resource) {
      if (isset($this->hasRendered[$resource['uri']])) {
        continue;
      }
      $this->hasRendered[$resource['uri']] = true;

      $output[] = $this->renderResource($resource);
      $output[] = "\n";
    }
    return phutil_implode_html('', $output);
  }

  private function renderResource(array $resource) {
    $uri = $this->getURI($resource);
    switch ($resource['type']) {
      case 'css':
        return phutil_tag(
          'link',
          array(
            'rel'   => 'stylesheet',
            'type'  => 'text/css',
            'href'  => $uri,
          ));
      case 'js':
        return phutil_tag(
          'script',
          array(
            'type'  => 'text/javascript',
            'src'   => $uri,
          ),
          '');
    }
    throw new Exception("Unable to render resource.");
  }

  public function renderHTMLFooter() {
    $data = array();
    if ($this->metadata) {
      $json_metadata = AphrontResponse::encodeJSONForHTTPResponse(
        $this->metadata);
      $this->metadata = array();
    } else {
      $json_metadata = '{}';
    }
    // Even if there is no metadata on the page, Javelin uses the mergeData()
    // call to start dispatching the event queue.
    $data[] = 'JX.Stratcom.mergeData('.$this->metadataBlock.', '.
                                       $json_metadata.');';

    $onload = array();
    if ($this->behaviors) {
      $behaviors = $this->behaviors;
      $this->behaviors = array();

      $higher_priority_names = array(
        'refresh-csrf',
        'aphront-basic-tokenizer',
        'dark-console',
        'history-install',
      );

      $higher_priority_behaviors = array_select_keys(
        $behaviors,
        $higher_priority_names);

      foreach ($higher_priority_names as $name) {
        unset($behaviors[$name]);
      }

      $behavior_groups = array(
        $higher_priority_behaviors,
        $behaviors);

      foreach ($behavior_groups as $group) {
        if (!$group) {
          continue;
        }
        $group_json = AphrontResponse::encodeJSONForHTTPResponse(
          $group);
        $onload[] = 'JX.initBehaviors('.$group_json.')';
      }
    }

    if ($onload) {
      foreach ($onload as $func) {
        $data[] = 'JX.onload(function(){'.$func.'});';
      }
    }

    if ($data) {
      $data = implode("\n", $data);
      return self::renderInlineScript($data);
    } else {
      return '';
    }
  }

  public static function renderInlineScript($data) {
    if (stripos($data, '</script>') !== false) {
      throw new Exception(
        'Literal </script> is not allowed inside inline script.');
    }
    return hsprintf(
      // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
      // would need to send the document with XHTML content type.
      '<script type="text/javascript">%s</script>',
      phutil_safe_html($data));
  }

  public function buildAjaxResponse($payload, $error = null) {
    $response = array(
      'error'   => $error,
      'payload' => $payload,
    );

    if ($this->metadata) {
      $response['javelin_metadata'] = $this->metadata;
      $this->metadata = array();
    }

    if ($this->behaviors) {
      $response['javelin_behaviors'] = $this->behaviors;
      $this->behaviors = array();
    }

    $this->resolveResources();
    $resources = array();
    foreach ($this->packaged as $resource) {
      $resources[] = $this->getURI($resource);
    }
    if ($resources) {
      $response['javelin_resources'] = $resources;
    }

    return $response;
  }

  private function getURI($resource) {
    $uri = $resource['uri'];

    // In developer mode, we dump file modification times into the URI. When a
    // page is reloaded in the browser, any resources brought in by Ajax calls
    // do not trigger revalidation, so without this it's very difficult to get
    // changes to Ajaxed-in CSS to work (you must clear your cache or rerun
    // the map script). In production, we can assume the map script gets run
    // after changes, and safely skip this.
    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $root = dirname(phutil_get_library_root('phabricator')).'/webroot';
      if (isset($resource['disk'])) {
        $mtime = (int)filemtime($root.$resource['disk']);
      } else {
        $mtime = 0;
        foreach ($resource['symbols'] as $symbol) {
          $map = CelerityResourceMap::getInstance();
          $symbol_info = $map->lookupSymbolInformation($symbol);
          $mtime = max($mtime, (int)filemtime($root.$symbol_info['disk']));
        }
      }

      $uri = preg_replace('@^/res/@', '/res/'.$mtime.'T/', $uri);
    }

    return PhabricatorEnv::getCDNURI($uri);
  }

}
