<?php

final class PhabricatorEdgeChangeRecordTestCase extends PhabricatorTestCase {


  public function testEdgeStorageFormats() {
    $old_bulky = phutil_json_decode(<<<EOJSON
{
   "PHID-PROJ-5r2ed5v27xrgltvou5or" : {
      "dataID" : null,
      "dateCreated" : "1449170683",
      "dst" : "PHID-PROJ-5r2ed5v27xrgltvou5or",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "type" : "41",
      "data" : [],
      "seq" : "0"
   },
   "PHID-PROJ-wh32nih7q5scvc5lvipv" : {
      "dataID" : null,
      "seq" : "0",
      "type" : "41",
      "data" : [],
      "dst" : "PHID-PROJ-wh32nih7q5scvc5lvipv",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1449170691"
   },
   "PHID-PROJ-zfp44q7loir643b5i4v4" : {
      "dataID" : null,
      "type" : "41",
      "data" : [],
      "seq" : "0",
      "dateCreated" : "1449170668",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dst" : "PHID-PROJ-zfp44q7loir643b5i4v4"
   },
   "PHID-PROJ-amvkc5zw2gsy7tyvocug" : {
      "dataID" : null,
      "dst" : "PHID-PROJ-amvkc5zw2gsy7tyvocug",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448833330",
      "seq" : "0",
      "type" : "41",
      "data" : []
   },
   "PHID-PROJ-3cuwfuuh4pwqyuof2hhr" : {
      "dataID" : null,
      "seq" : "0",
      "type" : "41",
      "data" : [],
      "dst" : "PHID-PROJ-3cuwfuuh4pwqyuof2hhr",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448899367"
   },
   "PHID-PROJ-okljqs7prifhajtvia3t" : {
      "dataID" : null,
      "seq" : "0",
      "data" : [],
      "type" : "41",
      "dst" : "PHID-PROJ-okljqs7prifhajtvia3t",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448902756"
   }
}
EOJSON
      );

    $new_bulky = phutil_json_decode(<<<EOJSON
{
   "PHID-PROJ-5r2ed5v27xrgltvou5or" : {
      "dataID" : null,
      "dateCreated" : "1449170683",
      "dst" : "PHID-PROJ-5r2ed5v27xrgltvou5or",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "type" : "41",
      "data" : [],
      "seq" : "0"
   },
   "PHID-PROJ-wh32nih7q5scvc5lvipv" : {
      "dataID" : null,
      "seq" : "0",
      "type" : "41",
      "data" : [],
      "dst" : "PHID-PROJ-wh32nih7q5scvc5lvipv",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1449170691"
   },
   "PHID-PROJ-zfp44q7loir643b5i4v4" : {
      "dataID" : null,
      "type" : "41",
      "data" : [],
      "seq" : "0",
      "dateCreated" : "1449170668",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dst" : "PHID-PROJ-zfp44q7loir643b5i4v4"
   },
   "PHID-PROJ-amvkc5zw2gsy7tyvocug" : {
      "dataID" : null,
      "dst" : "PHID-PROJ-amvkc5zw2gsy7tyvocug",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448833330",
      "seq" : "0",
      "type" : "41",
      "data" : []
   },
   "PHID-PROJ-3cuwfuuh4pwqyuof2hhr" : {
      "dataID" : null,
      "seq" : "0",
      "type" : "41",
      "data" : [],
      "dst" : "PHID-PROJ-3cuwfuuh4pwqyuof2hhr",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448899367"
   },
   "PHID-PROJ-zzzzqs7prifhajtvia3t" : {
      "dataID" : null,
      "seq" : "0",
      "data" : [],
      "type" : "41",
      "dst" : "PHID-PROJ-zzzzqs7prifhajtvia3t",
      "src" : "PHID-PSTE-5uj6oqv4kmhtr6ctwcq7",
      "dateCreated" : "1448902756"
   }
}
EOJSON
      );

    $old_slim = array(
      'PHID-PROJ-okljqs7prifhajtvia3t',
    );

    $new_slim = array(
      'PHID-PROJ-zzzzqs7prifhajtvia3t',
    );

    $bulky_xaction = new ManiphestTransaction();
    $bulky_xaction->setOldValue($old_bulky);
    $bulky_xaction->setNewValue($new_bulky);

    $slim_xaction = new ManiphestTransaction();
    $slim_xaction->setOldValue($old_slim);
    $slim_xaction->setNewValue($new_slim);

    $bulky_record = PhabricatorEdgeChangeRecord::newFromTransaction(
      $bulky_xaction);

    $slim_record = PhabricatorEdgeChangeRecord::newFromTransaction(
      $slim_xaction);

    $this->assertEqual(
      $bulky_record->getAddedPHIDs(),
      $slim_record->getAddedPHIDs());

    $this->assertEqual(
      $bulky_record->getRemovedPHIDs(),
      $slim_record->getRemovedPHIDs());
  }

}
