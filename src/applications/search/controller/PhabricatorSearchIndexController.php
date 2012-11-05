<?php

/**
 * @group search
 */
final class PhabricatorSearchIndexController
  extends PhabricatorSearchBaseController {

  private $phid;

  public function shouldRequireAdmin() {
    // This basically shows you all the text of any object in the system, so
    // make it admin-only.
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {

    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
    $document = $engine->reconstructDocument($this->phid);
    if (!$document) {
      return new Aphront404Response();
    }

    $panels = array();

    $panel = new AphrontPanelView();
    $panel->setHeader('Abstract Document Index');

    $props = array(
      'PHID'  => phutil_escape_html($document->getPHID()),
      'Title' => phutil_escape_html($document->getDocumentTitle()),
      'Type'  => phutil_escape_html($document->getDocumentType()),
    );
    $rows = array();
    foreach ($props as $name => $value) {
      $rows[] = array($name, $value);
    }
    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        '',
      ));
    $panel->appendChild($table);
    $panels[] = $panel;


    $panel = new AphrontPanelView();
    $panel->setHeader('Document Fields');

    $fields = $document->getFieldData();
    $rows = array();
    foreach ($fields as $field) {
      list($name, $corpus, $aux_phid) = $field;
      $rows[] = array(
        phutil_escape_html($name),
        phutil_escape_html(nonempty($aux_phid, null)),
        str_replace("\n", '<br />', phutil_escape_html($corpus)),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Field',
        'Aux PHID',
        'Corpus',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
      ));
    $panel->appendChild($table);
    $panels[] = $panel;


    $panel = new AphrontPanelView();
    $panel->setHeader('Document Relationships');

    $relationships = $document->getRelationshipData();

    $phids = ipull($relationships, 1);
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($relationships as $relationship) {
      list($type, $phid, $rtype, $time) = $relationship;
      $rows[] = array(
        phutil_escape_html($type),
        phutil_escape_html($phid),
        phutil_escape_html($rtype),
        $handles[$phid]->renderLink(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Relationship',
        'Related PHID',
        'Related Type',
        'Related Handle',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        'wide',
      ));
    $panel->appendChild($table);
    $panels[] = $panel;


    return $this->buildStandardPageResponse(
      $panels,
      array(
        'title' => 'Raw Index',
      ));
  }

}
