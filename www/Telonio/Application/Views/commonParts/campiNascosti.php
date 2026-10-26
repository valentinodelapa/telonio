<?php

use Telonio\Application\HelperClasses\Html;

?>
<input type="hidden" name="submitted" value="1">
<input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
