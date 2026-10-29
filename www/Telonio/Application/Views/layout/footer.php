<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Versioni;

?>
                <footer class="suite-versione small text-body-secondary border-top mt-5 pt-2">
                    Telonio <?= Html::e(Versioni::getSuite() ?? '') ?>
                    <?php if (($moduloAttivo ?? null) !== null && $moduloAttivo->getVersione() !== null): ?>
                        · <?= Html::e($moduloAttivo->getLabel()) ?> <?= Html::e($moduloAttivo->getVersione()) ?>
                    <?php endif; ?>
                </footer>
            </main>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="/javascript/suite.js"></script>
    </body>
</html>
