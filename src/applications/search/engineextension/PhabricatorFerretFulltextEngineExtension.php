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

      $virtual_fields[] = array(
        PhabricatorSearchDocumentFieldType::FIELD_ALL,
        $raw_corpus,
      );
    }

    $empty_template = array(
      'raw' => array(),
      'term' => array(),
      'normal' => array(),
    );

    $ferret_corpus_map = array();

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

      $ferret_fields[] = array(
        'fieldKey' => $key,
        'rawCorpus' => $raw_corpus,
        'termCorpus' => $term_corpus,
        'normalCorpus' => $normal_corpus,
      );
    }
    $ngrams_source = implode("\n", $ngrams_source);

    $ngrams = $engine->getTermNgramsFromString($ngrams_source);

    $object->openTransaction();

    try {
      $conn = $object->establishConnection('w');
      $this->deleteOldDocument($engine, $object, $document);

      queryfx(
        $conn,
        'INSERT INTO %T (objectPHID, isClosed, epochCreated, epochModified,
          authorPHID, ownerPHID) VALUES (%s, %d, %d, %d, %ns, %ns)',
        $engine->getDocumentTableName(),
        $object->getPHID(),
        $is_closed,
        $document->getDocumentCreated(),
        $document->getDocumentModified(),
        $author_phid,
        $owner_phid);

      $document_id = $conn->getInsertID();
      foreach ($ferret_fields as $ferret_field) {
        queryfx(
          $conn,
          'INSERT INTO %T (documentID, fieldKey, rawCorpus, termCorpus,
            normalCorpus) VALUES (%d, %s, %s, %s, %s)',
            $engine->getFieldTableName(),
            $document_id,
            $ferret_field['fieldKey'],
            $ferret_field['rawCorpus'],
            $ferret_field['termCorpus'],
            $ferret_field['normalCorpus']);
      }

      if ($ngrams) {
        $common = queryfx_all(
          $conn,
          'SELECT ngram FROM %T WHERE ngram IN (%Ls)',
          $engine->getCommonNgramsTableName(),
          $ngrams);
        $common = ipull($common, 'ngram', 'ngram');

        foreach ($ngrams as $key => $ngram) {
          if (isset($common[$ngram])) {
            unset($ngrams[$key]);
            continue;
          }

          // NOTE: MySQL discards trailing whitespace in CHAR(X) columns.
          $trim_ngram = rtrim($ngram, ' ');
          if (isset($common[$ngram])) {
            unset($ngrams[$key]);
            continue;
          }
        }
      }

      if ($ngrams) {
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
            $engine->getNgramsTableName(),
            $chunk);
        }
      }
    } catch (Exception $ex) {
      $object->killTransaction();
      throw $ex;
    }

    $object->saveTransaction();
  }


  private function deleteOldDocument(
    PhabricatorFerretEngine $engine,
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $conn = $object->establishConnection('w');

    $old_document = queryfx_one(
      $conn,
      'SELECT * FROM %T WHERE objectPHID = %s',
      $engine->getDocumentTableName(),
      $object->getPHID());
    if (!$old_document) {
      return;
    }

    $old_id = $old_document['id'];

    queryfx(
      $conn,
      'DELETE FROM %T WHERE id = %d',
      $engine->getDocumentTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->getFieldTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->getNgramsTableName(),
      $old_id);
  }

}
