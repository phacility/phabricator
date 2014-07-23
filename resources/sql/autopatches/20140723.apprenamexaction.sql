UPDATE {$NAMESPACE}_differential.differential_transaction
  SET authorPHID = 'PHID-APPS-PhabricatorHeraldApplication'
  WHERE authorPHID = 'PHID-APPS-PhabricatorApplicationHerald';
UPDATE {$NAMESPACE}_maniphest.maniphest_transaction
  SET authorPHID = 'PHID-APPS-PhabricatorHeraldApplication'
  WHERE authorPHID = 'PHID-APPS-PhabricatorApplicationHerald';
UPDATE {$NAMESPACE}_pholio.pholio_transaction
  SET authorPHID = 'PHID-APPS-PhabricatorHeraldApplication'
  WHERE authorPHID = 'PHID-APPS-PhabricatorApplicationHerald';

UPDATE {$NAMESPACE}_differential.differential_transaction
  SET authorPHID = 'PHID-APPS-PhabricatorHarbormasterApplication'
  WHERE authorPHID = 'PHID-APPS-PhabricatorApplicationHarbormaster';
