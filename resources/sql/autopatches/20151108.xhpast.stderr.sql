ALTER TABLE {$NAMESPACE}_xhpastview.xhpastview_parsetree
  ADD returnCode INT NOT NULL AFTER input;

ALTER TABLE {$NAMESPACE}_xhpastview.xhpastview_parsetree
  ADD stderr longtext NOT NULL AFTER stdout;
