<?php

final class DifferentialCreateInlineConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.createinline';
  }

  public function getMethodDescription() {
    return pht('Add an inline comment to a Differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      'revisionID' => 'optional revisionid',
      'diffID'     => 'optional diffid',
      'filePath'   => 'required string',
      'isNewFile'  => 'required bool',
      'lineNumber' => 'required int',
      'lineLength' => 'optional int',
      'content'    => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-REVISION' => pht(
        'Bad revision ID.'),
      'ERR-BAD-DIFF'     => pht(
        'Bad diff ID, or diff does not belong to revision.'),
      'ERR-NEED-DIFF'    => pht(
        'Neither revision ID nor diff ID was provided.'),
      'ERR-NEED-FILE'    => pht(
        'A file path was not provided.'),
      'ERR-BAD-FILE'     => pht(
        "Requested file doesn't exist in this revision."),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $rid = $request->getValue('revisionID');
    $did = $request->getValue('diffID');

    if ($rid) {
      // Given both a revision and a diff, check that they match.
      // Given only a revision, find the active diff.
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($rid))
        ->executeOne();
      if (!$revision) {
        throw new ConduitException('ERR-BAD-REVISION');
      }

      if (!$did) { // did not!
        $diff = $revision->loadActiveDiff();
        $did = $diff->getID();
      } else { // did too!
        $diff = id(new DifferentialDiff())->load($did);
        if (!$diff || $diff->getRevisionID() != $rid) {
          throw new ConduitException('ERR-BAD-DIFF');
        }
      }
    } else if ($did) {
      // Given only a diff, find the parent revision.
      $diff = id(new DifferentialDiff())->load($did);
      if (!$diff) {
        throw new ConduitException('ERR-BAD-DIFF');
      }
      $rid = $diff->getRevisionID();
    } else {
      // Given neither, bail.
      throw new ConduitException('ERR-NEED-DIFF');
    }

    $file = $request->getValue('filePath');
    if (!$file) {
      throw new ConduitException('ERR-NEED-FILE');
    }
    $changes = id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $did);
    $cid = null;
    foreach ($changes as $id => $change) {
      if ($file == $change->getFilename()) {
        $cid = $id;
      }
    }
    if ($cid == null) {
      throw new ConduitException('ERR-BAD-FILE');
    }

    $inline = id(new DifferentialInlineComment())
      ->setRevisionID($rid)
      ->setChangesetID($cid)
      ->setAuthorPHID($request->getUser()->getPHID())
      ->setContent($request->getValue('content'))
      ->setIsNewFile($request->getValue('isNewFile'))
      ->setLineNumber($request->getValue('lineNumber'))
      ->setLineLength($request->getValue('lineLength', 0))
      ->save();

    // Load everything again, just to be safe.
    $changeset = id(new DifferentialChangeset())
      ->load($inline->getChangesetID());
    return $this->buildInlineInfoDictionary($inline, $changeset);
  }

}
