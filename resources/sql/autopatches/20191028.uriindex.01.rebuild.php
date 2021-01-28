<?php

// @phase worker

PhabricatorRebuildIndexesWorker::rebuildObjectsWithQuery(
  'PhabricatorRepositoryQuery');
