<?php

/**
 * Tracks and resolves dependencies the page declares with
 * @{function:require_celerity_resource}, and then builds appropriate HTML or
 * Ajax responses.
 */
final class CelerityStaticResourceResponse extends Phobject {

  private $symbols = array();
  private $needsResolve = true;
  private $resolved;
  private $packaged;
  private $metadata = array();
  private $metadataBlock = 0;
  private $metadataLocked;
  private $behaviors = array();
  private $hasRendered = array();
  private $postprocessorKey;

  public function __construct() {
    if (isset($_REQUEST['__metablock__'])) {
      $this->metadataBlock = (int)$_REQUEST['__metablock__'];
    }
  }

  public function addMetadata($metadata) {
    if ($this->metadataLocked) {
      throw new Exception(
        pht(
          'Attempting to add more metadata after metadata has been '.
          'locked.'));
    }

    $id = count($this->metadata);
    $this->metadata[$id] = $metadata;
    return $this->metadataBlock.'_'.$id;
  }

  public function getMetadataBlock() {
    return $this->metadataBlock;
  }

  public function setPostprocessorKey($postprocessor_key) {
    $this->postprocessorKey = $postprocessor_key;
    return $this;
  }

  public function getPostprocessorKey() {
    return $this->postprocessorKey;
  }

  /**
   * Register a behavior for initialization.
   *
   * NOTE: If `$config` is empty, a behavior will execute only once even if it
   * is initialized multiple times. If `$config` is nonempty, the behavior will
   * be invoked once for each configuration.
   */
  public function initBehavior(
    $behavior,
    array $config = array(),
    $source_name = null) {

    $this->requireResource('javelin-behavior-'.$behavior, $source_name);

    if (empty($this->behaviors[$behavior])) {
      $this->behaviors[$behavior] = array();
    }

    if ($config) {
      $this->behaviors[$behavior][] = $config;
    }

    return $this;
  }

  public function requireResource($symbol, $source_name) {
    if (isset($this->symbols[$source_name][$symbol])) {
      return $this;
    }

    // Verify that the resource exists.
    $map = CelerityResourceMap::getNamedInstance($source_name);
    $name = $map->getResourceNameForSymbol($symbol);
    if ($name === null) {
      throw new Exception(
        pht(
          'No resource with symbol "%s" exists in source "%s"!',
          $symbol,
          $source_name));
    }

    $this->symbols[$source_name][$symbol] = true;
    $this->needsResolve = true;

    return $this;
  }

  private function resolveResources() {
    if ($this->needsResolve) {
      $this->packaged = array();
      foreach ($this->symbols as $source_name => $symbols_map) {
        $symbols = array_keys($symbols_map);

        $map = CelerityResourceMap::getNamedInstance($source_name);
        $packaged = $map->getPackagedNamesForSymbols($symbols);

        $this->packaged[$source_name] = $packaged;
      }
      $this->needsResolve = false;
    }
    return $this;
  }

  public function renderSingleResource($symbol, $source_name) {
    $map = CelerityResourceMap::getNamedInstance($source_name);
    $packaged = $map->getPackagedNamesForSymbols(array($symbol));
    return $this->renderPackagedResources($map, $packaged);
  }

  public function renderResourcesOfType($type) {
    $this->resolveResources();

    $result = array();
    foreach ($this->packaged as $source_name => $resource_names) {
      $map = CelerityResourceMap::getNamedInstance($source_name);

      $resources_of_type = array();
      foreach ($resource_names as $resource_name) {
        $resource_type = $map->getResourceTypeForName($resource_name);
        if ($resource_type == $type) {
          $resources_of_type[] = $resource_name;
        }
      }

      $result[] = $this->renderPackagedResources($map, $resources_of_type);
    }

    return phutil_implode_html('', $result);
  }

  private function renderPackagedResources(
    CelerityResourceMap $map,
    array $resources) {

    $output = array();
    foreach ($resources as $name) {
      if (isset($this->hasRendered[$name])) {
        continue;
      }
      $this->hasRendered[$name] = true;

      $output[] = $this->renderResource($map, $name);
    }

    return $output;
  }

  private function renderResource(
    CelerityResourceMap $map,
    $name) {

    $uri = $this->getURI($map, $name);
    $type = $map->getResourceTypeForName($name);

    $multimeter = MultimeterControl::getInstance();
    if ($multimeter) {
      $event_type = MultimeterEvent::TYPE_STATIC_RESOURCE;
      $multimeter->newEvent($event_type, 'rsrc.'.$name, 1);
    }

    switch ($type) {
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

    throw new Exception(
      pht(
        'Unable to render resource "%s", which has unknown type "%s".',
        $name,
        $type));
  }

  public function renderHTMLFooter() {
    $this->metadataLocked = true;

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
        $behaviors,
      );

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
        pht(
          'Literal %s is not allowed inside inline script.',
          '</script>'));
    }
    if (strpos($data, '<!') !== false) {
      throw new Exception(
        pht(
          'Literal %s is not allowed inside inline script.',
          '<!'));
    }
    // We don't use <![CDATA[ ]]> because it is ignored by HTML parsers. We
    // would need to send the document with XHTML content type.
    return phutil_tag(
      'script',
      array('type' => 'text/javascript'),
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
    foreach ($this->packaged as $source_name => $resource_names) {
      $map = CelerityResourceMap::getNamedInstance($source_name);
      foreach ($resource_names as $resource_name) {
        $resources[] = $this->getURI($map, $resource_name);
      }
    }
    if ($resources) {
      $response['javelin_resources'] = $resources;
    }

    return $response;
  }

  public function getURI(
    CelerityResourceMap $map,
    $name,
    $use_primary_domain = false) {

    $uri = $map->getURIForName($name);

    // If we have a postprocessor selected, add it to the URI.
    $postprocessor_key = $this->getPostprocessorKey();
    if ($postprocessor_key) {
      $uri = preg_replace('@^/res/@', '/res/'.$postprocessor_key.'X/', $uri);
    }

    // In developer mode, we dump file modification times into the URI. When a
    // page is reloaded in the browser, any resources brought in by Ajax calls
    // do not trigger revalidation, so without this it's very difficult to get
    // changes to Ajaxed-in CSS to work (you must clear your cache or rerun
    // the map script). In production, we can assume the map script gets run
    // after changes, and safely skip this.
    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $mtime = $map->getModifiedTimeForName($name);
      $uri = preg_replace('@^/res/@', '/res/'.$mtime.'T/', $uri);
    }

    if ($use_primary_domain) {
      return PhabricatorEnv::getURI($uri);
    } else {
      return PhabricatorEnv::getCDNURI($uri);
    }
  }

}
