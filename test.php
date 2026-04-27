<?php
$hash = '$2y$10$8D2ZZgWKVov8PbW1cmFOo.WaNCrtiFpbyAhkq7lNpYMkTRilhq9mu';

var_dump(password_verify('password', $hash));