UPDATE
  {$NAMESPACE}_drydock.drydock_lease l,
  {$NAMESPACE}_drydock.drydock_resource r
  SET l.resourcePHID = r.phid
  WHERE l.resourceID = r.id;
