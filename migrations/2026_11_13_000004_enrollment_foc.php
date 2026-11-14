<?php
return ['up'=>[
    "ALTER TABLE enrollments ADD is_foc TINYINT(1) NOT NULL DEFAULT 0"
], 'down'=>[
    "ALTER TABLE enrollments DROP is_foc"
]];
