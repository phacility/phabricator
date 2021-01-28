<?php

// @phase worker

PhabricatorRebuildIndexesWorker::rebuildObjectsWithQuery(
  'PhabricatorDashboardQuery');

PhabricatorRebuildIndexesWorker::rebuildObjectsWithQuery(
  'PhabricatorDashboardPanelQuery');
