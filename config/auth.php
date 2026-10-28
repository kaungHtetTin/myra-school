<?php

// Supply your own callbacks before marking routes as protected.
return ['check' => fn() => isset($_SESSION['user']), 'can' => fn() => isset($_SESSION['user']), 'login' => 'login'];
