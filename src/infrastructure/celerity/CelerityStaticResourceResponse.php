<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
  private $useFullURI = false;

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

  /**
   * If set to true, Celerity will print full URIs (including the domain)
   * for static resources.
   */
  public function setUseFullURI($should) {
    $this->useFullURI = $should;
    return $this;
  }

  private function shouldUseFullURI() {
    return $this->useFullURI;
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
    }
    return implode("\n", $output);
  }

  private function renderResource(array $resource) {
    $uri = $this->getURI($resource['uri']);
    switch ($resource['type']) {
      case 'css':
        return '<link rel="stylesheet" type="text/css" href="'.$uri.'" />';
      case 'js':
        return '<script type="text/javascript" src="'.$uri.'">'.
               '</script>';
    }
    throw new Exception("Unable to render resource.");
  }

  private function getURI($path) {
    $path = phutil_escape_html($path);
    if ($this->shouldUseFullURI()) {
      $uri = PhabricatorEnv::getCDNURI($path);
    } else {
      $uri = $path;
    }
    return $uri;
  }

  public function renderHTMLFooter() {
    $data = array();
    if ($this->metadata) {
      $json_metadata = json_encode($this->metadata);
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
        $onload[] = 'JX.initBehaviors('.json_encode($group).')';
      }
    }

    if ($onload) {
      foreach ($onload as $func) {
        $data[] = 'JX.onload(function(){'.$func.'});';
      }
    }

    if ($data) {
      $data = implode("\n", $data);
      return '<script type="text/javascript">//<![CDATA['."\n".
             $data.'//]]></script>';
    } else {
      return '';
    }
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

    return $response;
  }

}
