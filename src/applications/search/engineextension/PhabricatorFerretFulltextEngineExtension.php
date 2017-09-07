<?php

final class PhabricatorFerretFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'ferret';


  public function getExtensionName() {
    return pht('Ferret Fulltext Engine');
  }


  public function shouldIndexFulltextObject($object) {
    return ($object instanceof PhabricatorFerretInterface);
  }


  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $phid = $document->getPHID();
    $engine = $object->newFerretEngine();

    $is_closed = 0;
    $author_phid = null;
    $owner_phid = null;
    foreach ($document->getRelationshipData() as $relationship) {
      list($related_type, $related_phid) = $relationship;
      switch ($related_type) {
        case PhabricatorSearchRelationship::RELATIONSHIP_OPEN:
          $is_closed = 0;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_CLOSED:
          $is_closed = 1;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_OWNER:
          $owner_phid = $related_phid;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED:
          $owner_phid = null;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR:
          $author_phid = $related_phid;
          break;
      }
    }

    $ferret_document = $engine->newDocumentObject()
      ->setObjectPHID($phid)
      ->setIsClosed($is_closed)
      ->setEpochCreated($document->getDocumentCreated())
      ->setEpochModified($document->getDocumentModified())
      ->setAuthorPHID($author_phid)
      ->setOwnerPHID($owner_phid);

    $stemmer = $engine->newStemmer();

    // Copy all of the "title" and "body" fields to create new "core" fields.
    // This allows users to search "in title or body" with the "core:" prefix.
    $document_fields = $document->getFieldData();
    $virtual_fields = array();
    foreach ($document_fields as $field) {
      $virtual_fields[] = $field;

      list($key, $raw_corpus) = $field;
      switch ($key) {
        case PhabricatorSearchDocumentFieldType::FIELD_TITLE:
        case PhabricatorSearchDocumentFieldType::FIELD_BODY:
          $virtual_fields[] = array(
            PhabricatorSearchDocumentFieldType::FIELD_CORE,
            $raw_corpus,
          );
          break;
      }
    }

    $key_all = PhabricatorSearchDocumentFieldType::FIELD_ALL;

    $empty_template = array(
      'raw' => array(),
      'term' => array(),
      'normal' => array(),
    );

    $ferret_corpus_map = array(
      $key_all => $empty_template,
    );

    foreach ($virtual_fields as $field) {
      list($key, $raw_corpus) = $field;
      if (!strlen($raw_corpus)) {
        continue;
      }

      $term_corpus = $engine->newTermsCorpus($raw_corpus);

      $normal_corpus = $stemmer->stemCorpus($raw_corpus);
      $normal_corpus = $engine->newTermsCorpus($normal_corpus);

      if (!isset($ferret_corpus_map[$key])) {
        $ferret_corpus_map[$key] = $empty_template;
      }

      $ferret_corpus_map[$key]['raw'][] = $raw_corpus;
      $ferret_corpus_map[$key]['term'][] = $term_corpus;
      $ferret_corpus_map[$key]['normal'][] = $normal_corpus;

      $ferret_corpus_map[$key_all]['raw'][] = $raw_corpus;
      $ferret_corpus_map[$key_all]['term'][] = $term_corpus;
      $ferret_corpus_map[$key_all]['normal'][] = $normal_corpus;
    }

    $ferret_fields = array();
    $ngrams_source = array();
    foreach ($ferret_corpus_map as $key => $fields) {
      $raw_corpus = $fields['raw'];
      $raw_corpus = implode("\n", $raw_corpus);
      if (strlen($raw_corpus)) {
        $ngrams_source[] = $raw_corpus;
      }

      $normal_corpus = $fields['normal'];
      $normal_corpus = implode("\n", $normal_corpus);
      if (strlen($normal_corpus)) {
        $ngrams_source[] = $normal_corpus;
      }

      $term_corpus = $fields['term'];
      $term_corpus = implode("\n", $term_corpus);
      if (strlen($term_corpus)) {
        $ngrams_source[] = $term_corpus;
      }

      $ferret_fields[] = $engine->newFieldObject()
        ->setFieldKey($key)
        ->setRawCorpus($raw_corpus)
        ->setTermCorpus($term_corpus)
        ->setNormalCorpus($normal_corpus);
    }
    $ngrams_source = implode("\n", $ngrams_source);

    $ngrams = $engine->getTermNgramsFromString($ngrams_source);

    $ferret_document->openTransaction();

    try {
      $this->deleteOldDocument($engine, $object, $document);

      $ferret_document->save();

      $document_id = $ferret_document->getID();
      foreach ($ferret_fields as $ferret_field) {
        $ferret_field
          ->setDocumentID($document_id)
          ->save();
      }

      $ferret_ngrams = $engine->newNgramsObject();
      $conn = $ferret_ngrams->establishConnection('w');

      $sql = array();
      foreach ($ngrams as $ngram) {
        $sql[] = qsprintf(
          $conn,
          '(%d, %s)',
          $document_id,
          $ngram);
      }

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT INTO %T (documentID, ngram) VALUES %Q',
          $ferret_ngrams->getTableName(),
          $chunk);
      }
    } catch (Exception $ex) {
      $ferret_document->killTransaction();
      throw $ex;
    }

    $ferret_document->saveTransaction();
  }


  private function deleteOldDocument(
    PhabricatorFerretEngine $engine,
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $old_document = $engine->newDocumentObject()->loadOneWhere(
      'objectPHID = %s',
      $document->getPHID());
    if (!$old_document) {
      return;
    }

    $conn = $old_document->establishConnection('w');
    $old_id = $old_document->getID();

    queryfx(
      $conn,
      'DELETE FROM %T WHERE id = %d',
      $engine->newDocumentObject()->getTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->newFieldObject()->getTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->newNgramsObject()->getTableName(),
      $old_id);
  }

}
