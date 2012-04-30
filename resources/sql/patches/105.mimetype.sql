/* Prior to D1615, we used the raw output of `file` to determine mime types,
   without stripping carriage returns. This creates Content-Type headers
   which are blocked by response-splitting protections introduced in D1564. */
UPDATE {$NAMESPACE}_file.file SET mimeType = TRIM(BOTH "\n" FROM mimeType);
