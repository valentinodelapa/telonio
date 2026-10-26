<?php

namespace Telonio\Tests\Application\HelperClasses;

use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Core\CustomTypes\FormFilterError;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

class HtmlAndCampoAndLayoutTest extends SuiteTestCase
{

    public function testHtmlEscapeEData(): void
    {
        $this->assertSame('&lt;b&gt;&quot;Mario&quot; &amp; &#039;Rossi&#039;&lt;/b&gt;', Html::e('<b>"Mario" & \'Rossi\'</b>'));
        $this->assertSame('', Html::data(null));
        $data = new \DateTimeImmutable('2025-03-15 14:30:00');
        $this->assertSame('15/03/2025', Html::data($data));
        $this->assertSame('2025-03-15', Html::data($data, 'Y-m-d'));
    }

    public function testHtmlValoreSuTuttiITipi(): void
    {
        $this->assertSame('', Html::valore(null, 'qualsiasi'));

        $studio = $this->creaStudio(42, 'Studio <Test>');
        $oggetto = (object) [
            'entita' => $studio,
            'sismaDate' => new SismaDate('2025-04-20'),
            'dateTime' => new SismaDateTime('2025-04-20 10:15:00'),
            'enum' => RuoloUtente::amministratore,
            'vero' => true,
            'falso' => false,
            'testo' => 'A & B',
        ];

        $this->assertSame('', Html::valore($oggetto, 'inesistente'));
        $this->assertSame('42', Html::valore($oggetto, 'entita'));
        $this->assertSame('2025-04-20', Html::valore($oggetto, 'sismaDate'));
        $this->assertSame('2025-04-20T10:15', Html::valore($oggetto, 'dateTime'));
        $this->assertSame('amministratore', Html::valore($oggetto, 'enum'));
        $this->assertSame('1', Html::valore($oggetto, 'vero'));
        $this->assertSame('0', Html::valore($oggetto, 'falso'));
        $this->assertSame('A &amp; B', Html::valore($oggetto, 'testo'));
    }

    public function testHtmlSelezionatoSpuntatoEErrori(): void
    {
        $oggetto = (object) [
            'ruolo' => RuoloUtente::operatore,
            'attivo' => true,
            'disattivo' => false,
        ];
        $this->assertSame('selected', Html::selezionato($oggetto, 'ruolo', 'operatore'));
        $this->assertSame('', Html::selezionato($oggetto, 'ruolo', 'amministratore'));
        $this->assertSame('checked', Html::spuntato($oggetto, 'attivo'));
        $this->assertSame('', Html::spuntato($oggetto, 'disattivo'));

        $errori = new FormFilterError();
        $this->assertSame('', Html::classeErrore($errori, 'campo'));
        $this->assertSame('', Html::messaggioErrore($errori, 'campo', 'Errore predefinito'));

        $errori->campoError = true;
        $this->assertSame('is-invalid', Html::classeErrore($errori, 'campo'));
        $this->assertSame('<div class="invalid-feedback">Errore predefinito</div>', Html::messaggioErrore($errori, 'campo', 'Errore predefinito'));

        $errori->campoCustomMessage = 'Messaggio <personalizzato>';
        $this->assertSame('is-invalid', Html::classeErrore($errori, 'campo'));
        $this->assertSame('<div class="invalid-feedback">Messaggio &lt;personalizzato&gt;</div>', Html::messaggioErrore($errori, 'campo', 'Errore predefinito'));
    }

    public function testMetodiDiCampo(): void
    {
        $origine = (object) [
            'denominazione' => 'Studio Rossi',
            'dataInizio' => new SismaDate('2025-01-10'),
            'note' => 'Nota di prova',
            'tipo' => 'B',
            'ruolo' => RuoloUtente::amministratore,
            'attivo' => true,
            'soggetto' => $this->creaStudio(7),
        ];
        $errori = new FormFilterError();
        $errori->denominazioneCustomMessage = 'Denominazione errata';

        $testo = Campo::testo('denominazione', 'Denominazione', $origine, $errori, [
            'richiesto' => true,
            'aiuto' => 'Inserire il nome',
            'prefisso' => 'riga',
            'colonna' => 'col-md-12',
        ]);
        $this->assertStringContainsString('col-md-12', $testo);
        $this->assertStringContainsString('id="riga_denominazione"', $testo);
        $this->assertStringContainsString('name="riga[denominazione]"', $testo);
        $this->assertStringContainsString('value="Studio Rossi"', $testo);
        $this->assertStringContainsString('required', $testo);
        $this->assertStringContainsString('is-invalid', $testo);
        $this->assertStringContainsString('Denominazione errata', $testo);
        $this->assertStringContainsString('<div class="form-text">Inserire il nome</div>', $testo);

        $data = Campo::data('dataInizio', 'Data inizio', $origine, $errori);
        $this->assertStringContainsString('type="date"', $data);
        $this->assertStringContainsString('value="2025-01-10"', $data);

        $password = Campo::password('password', 'Password', $errori, ['richiesto' => true]);
        $this->assertStringContainsString('type="password"', $password);
        $this->assertStringContainsString('value=""', $password);
        $this->assertStringContainsString('required', $password);

        $areaTesto = Campo::areaTesto('note', 'Note', $origine, $errori, ['righe' => 5]);
        $this->assertStringContainsString('rows="5"', $areaTesto);
        $this->assertStringContainsString('>Nota di prova</textarea>', $areaTesto);

        $selezione = Campo::selezione('tipo', 'Tipo', $origine, $errori, ['A' => 'Opzione A', 'B' => 'Opzione B'], ['vuota' => 'Seleziona...']);
        $this->assertStringContainsString('<option value="">Seleziona...</option>', $selezione);
        $this->assertStringContainsString('<option value="B" selected>Opzione B</option>', $selezione);

        $selezioneEnum = Campo::selezioneEnum('ruolo', 'Ruolo', $origine, $errori, RuoloUtente::cases());
        $this->assertStringContainsString('<option value="amministratore" selected>Amministratore</option>', $selezioneEnum);
        $this->assertStringContainsString('<option value="operatore" >Operatore</option>', $selezioneEnum);

        $spunta = Campo::spunta('attivo', 'Utente attivo', $origine, $errori);
        $this->assertStringContainsString('type="checkbox"', $spunta);
        $this->assertStringContainsString('checked', $spunta);
        $this->assertStringContainsString('Utente attivo', $spunta);

        $autocompleta = Campo::autocompleta('soggetto', 'Soggetto', $origine, $errori, '/soggetto/cerca', 'Rossi Mario', [
            'campoData' => 'dataInizio',
            'richiesto' => true,
        ]);
        $this->assertStringContainsString('data-url="/soggetto/cerca"', $autocompleta);
        $this->assertStringContainsString('data-campo-data="dataInizio"', $autocompleta);
        $this->assertStringContainsString('value="Rossi Mario"', $autocompleta);
        $this->assertStringContainsString('name="soggetto" value="7"', $autocompleta);
    }

    public function testLayoutPercorsoRestituisceFileVistaEsistente(): void
    {
        $percorsoHeader = Layout::percorso('header');
        $this->assertStringEndsWith('Views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'header.php', $percorsoHeader);
        $this->assertFileExists($percorsoHeader);
    }
}
