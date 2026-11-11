<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Interventi edilizi demo sull'abitazione principale di Mario Rossi, per
 * il quadro RP del modello Redditi PF:
 * - ristrutturazione del 2021 (la variazione catastale del 2022), 60.000
 *   euro al 50 per cento in 10 rate: quinta rata nel 2025;
 * - rifacimento delle parti comuni condominiali del 2024, quota di 3.000
 *   euro al 50 per cento (casella "Condominio" dei dati catastali);
 * - schermature solari del 2023 (risparmio energetico, tipo 5) al 50 per
 *   cento: terza rata;
 * - rifacimento del bagno del 2025 sull'abitazione principale (casella
 *   "Percentuale" 3, 50 per cento) e mobili per 5.000 euro (bonus mobili):
 *   prima rata.
 */
class InterventiCasaRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaRossiFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(PossessoCasaRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $crea = function (TipoDetrazioneEdilizia $tipo, int $anno, ?int $tipologia, ?int $codicePercentuale, float $spesa, string $descrizione, bool $condominio = false): InterventoEdilizio {
            $intervento = new InterventoEdilizio($this->dataMapper);
            $intervento->immobile = $this->getEntityByFixtureName(ImmobileCasaRossiFixture::class);
            $intervento->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
            $intervento->tipo = $tipo;
            $intervento->anno = $anno;
            $intervento->tipologia = $tipologia;
            $intervento->codicePercentuale = $codicePercentuale;
            $intervento->spesa = $spesa;
            $intervento->percentualeDetrazione = 50.0;
            $intervento->rateazione = 10;
            $intervento->condominio = $condominio;
            $intervento->descrizione = $descrizione;
            return $intervento;
        };
        $this->dataMapper->save($crea(TipoDetrazioneEdilizia::ristrutturazione, 2021, null, null, 60000.0, 'Ristrutturazione dell\'appartamento'));
        $condominio = $crea(TipoDetrazioneEdilizia::ristrutturazione, 2024, null, null, 3000.0, 'Rifacimento della facciata condominiale (quota millesimale)', true);
        $condominio->codiceFiscaleCondominio = '97123450583';
        $this->dataMapper->save($condominio);
        $this->dataMapper->save($crea(TipoDetrazioneEdilizia::risparmioEnergetico, 2023, 5, null, 8000.0, 'Schermature solari'));
        $this->dataMapper->save($crea(TipoDetrazioneEdilizia::arredo, 2025, null, null, 5000.0, 'Arredo del bagno ristrutturato'));
        $this->addEntity($crea(TipoDetrazioneEdilizia::ristrutturazione, 2025, null, 3, 12000.0, 'Rifacimento del bagno'));
    }
}
