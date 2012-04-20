<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class PhabricatorSearchEngineElastic extends PhabricatorSearchEngine {

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    $type = $doc->getDocumentType();
    $phid = $doc->getPHID();

    $spec = array(
      'phid'          => $phid,
      'type'          => $type,
      'title'         => $doc->getDocumentTitle(),
      'dateCreated'   => date('c', $doc->getDocumentCreated()),
      'dateModified'  => date('c', $doc->getDocumentModified()),
      'field'         => array(),
      'relationship'  => array(),
    );

    foreach ($doc->getFieldData() as $field) {
      list($ftype, $corpus, $aux_phid) = $field;
      $spec['field'][$ftype][] = array(
        'corpus'  => $corpus,
        'aux'     => $aux_phid,
      );
    }

    foreach ($doc->getRelationshipData() as $relationship) {
      list($rtype, $to_phid, $to_type, $time) = $relationship;
      $spec['relationship'][$rtype][] = array(
        'phid'      => $to_phid,
        'phidType'  => $to_type,
        'when'      => date('c', $time),
      );
    }

    $this->executeRequest(
      "/phabricator/{$type}/{$phid}/",
      $spec,
      $is_write = true);
  }

  public function reconstructDocument($phid) {

    $response = $this->executeRequest(
      '/phabricator/_search',
      array(
        'query' => array(
          'ids' => array(
            'values' => array(
              $phid,
            ),
          ),
        ),
      ),
      $is_write = false);

    $hit = $response['hits']['hits'][0]['_source'];
    if (!$hit) {
      return null;
    }

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($hit['phid']);
    $doc->setDocumentType($hit['type']);
    $doc->setDocumentTitle($hit['title']);
    $doc->setDocumentCreated(strtotime($hit['dateCreated']));
    $doc->setDocumentModified(strtotime($hit['dateModified']));

    foreach ($hit['field'] as $ftype => $fdefs) {
      foreach ($fdefs as $fdef) {
        $doc->addField(
          $ftype,
          $fdef['corpus'],
          $fdef['aux']);
      }
    }

    foreach ($hit['relationship'] as $rtype => $rships) {
      foreach ($rships as $rship) {
        $doc->addRelationship(
          $rtype,
          $rship['phid'],
          $rship['phidType'],
          strtotime($rship['when']));
      }
    }

    return $doc;
  }

  public function executeSearch(PhabricatorSearchQuery $query) {

    $spec = array(
      'text' => array(
        '_all' => $query->getQuery(),
      ),
    );

    $type = $query->getParameter('type');
    if ($type) {
      $uri = "/phabricator/{$type}/_search";
    } else {
      $uri = "/phabricator/_search";
    }

    $response = $this->executeRequest(
      $uri,
      array(
        'query' => $spec,
      ),
      $is_write = false);

    $phids = array();
    foreach ($response['hits']['hits'] as $hit) {
      $phids[] = $hit['_id'];
    }

    return $phids;
  }

  private function executeRequest($path, array $data, $is_write) {
    $uri = PhabricatorEnv::getEnvConfig('search.elastic.host');
    $uri = new PhutilURI($uri);
    $data = json_encode($data);

    $uri->setPath($path);

    $protocol = $uri->getProtocol();
    if ($protocol == 'https') {
      $future = new HTTPSFuture($uri, $data);
    } else {
      $future = new HTTPFuture($uri, $data);
    }

    if ($is_write) {
      $future->setMethod('PUT');
    } else {
      $future->setMethod('GET');
    }

    list($body) = $future->resolvex();

    if ($is_write) {
      return null;
    }

    $body = json_decode($body, true);
    if (!is_array($body)) {
      throw new Exception("elasticsearch server returned invalid JSON!");
    }

    return $body;
  }

}
