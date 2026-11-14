<?php
return ['up'=>[
    "ALTER TABLE students ADD notes TEXT NULL"
], 'down'=>[
    "ALTER TABLE students DROP notes"
]];
