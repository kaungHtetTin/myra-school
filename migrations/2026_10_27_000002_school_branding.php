<?php
return [
    'up' => ['ALTER TABLE settings ADD logo_path VARCHAR(190) NULL, ADD favicon_path VARCHAR(190) NULL'],
    'down' => ['ALTER TABLE settings DROP COLUMN logo_path, DROP COLUMN favicon_path'],
];
