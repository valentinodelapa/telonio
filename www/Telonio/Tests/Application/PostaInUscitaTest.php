<?php

namespace Telonio\Tests\Application;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Telonio\Application\Controllers\CasellaPostaController;
use Telonio\Application\Entities\CasellaPosta;
use Telonio\Application\Enumerations\CifraturaSmtp;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use Telonio\Application\Services\Posta\InvioPosta;
use Telonio\Application\Services\Posta\InvioPostaException;
use Telonio\Application\Services\Posta\Messaggio;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Core\Exceptions\AccessDeniedException;

/**
 * Posta in uscita dello studio: caselle e-mail e PEC, ripiego sui parametri
 * MAIL_* del .env, preparazione e invio del messaggio con PHPMailer (senza
 * connessione: il trasporto è sostituito), configurazione dall'interfaccia.
 */
class PostaInUscitaTest extends SuiteTestCase
{

    private const AMBIENTE = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];

    /** @var array<string, string|false> */
    private array $ambiente = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::AMBIENTE as $variabile) {
            $this->ambiente[$variabile] = getenv($variabile);
            putenv($variabile);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->ambiente as $variabile => $valore) {
            putenv($valore === false ? $variabile : $variabile . '=' . $valore);
        }
        parent::tearDown();
    }

    private function creaCasella(TipoCasellaPosta $tipo = TipoCasellaPosta::pec, ?string $utente = 'studio@pec.it'): CasellaPosta
    {
        $casella = new CasellaPosta($this->dataMapper);
        $casella->id = 1;
        $casella->studio = $this->studioPredefinito;
        $casella->tipoCasella = $tipo;
        $casella->host = 'smtps.pec.aruba.it';
        $casella->porta = 465;
        $casella->cifratura = CifraturaSmtp::sslTls;
        $casella->utente = $utente;
        $casella->password = $utente === null ? null : 'segreta';
        $casella->indirizzo = 'studio@pec.it';
        return $casella;
    }

    public function testEnumerazioni(): void
    {
        $this->assertSame('PEC', TipoCasellaPosta::pec->getLabel());
        $this->assertSame('bi-envelope', TipoCasellaPosta::email->getIcona());
        $this->assertSame(465, CifraturaSmtp::sslTls->getPortaPredefinita());
        $this->assertSame(587, CifraturaSmtp::starttls->getPortaPredefinita());
        $this->assertSame(25, CifraturaSmtp::nessuna->getPortaPredefinita());
        $this->assertSame(CifraturaSmtp::sslTls, CifraturaSmtp::daAmbiente('SSL'));
        $this->assertSame(CifraturaSmtp::starttls, CifraturaSmtp::daAmbiente('tls'));
        $this->assertSame(CifraturaSmtp::starttls, CifraturaSmtp::daAmbiente('starttls'));
        $this->assertSame(CifraturaSmtp::nessuna, CifraturaSmtp::daAmbiente('none'));
        $this->assertSame('STARTTLS', CifraturaSmtp::starttls->getLabel());
    }

    public function testCasellaAmbienteERipiego(): void
    {
        // senza MAIL_HOST non c'è casella dell'installazione
        $this->assertNull(InvioPosta::getCasellaAmbiente($this->studioPredefinito, $this->dataMapper));

        putenv('MAIL_HOST=mailpit');
        putenv('MAIL_ENCRYPTION=none');
        putenv('MAIL_FROM_ADDRESS=noreply@telonio.localhost');
        $casella = InvioPosta::getCasellaAmbiente($this->studioPredefinito, $this->dataMapper);
        $this->assertFalse($casella->isSalvata());
        $this->assertSame(25, $casella->porta);
        $this->assertNull($casella->utente);
        $this->assertSame('Studio Associato Test', $casella->getMittente());

        putenv('MAIL_PORT=1025');
        putenv('MAIL_USERNAME=utente');
        putenv('MAIL_PASSWORD=segreta');
        $casella = InvioPosta::getCasellaAmbiente($this->studioPredefinito, $this->dataMapper);
        $this->assertSame(1025, $casella->porta);
        $this->assertSame('utente', $casella->utente);
        $this->assertSame('segreta', $casella->password);

        // l'e-mail dello studio non configurata ricade sul .env, la PEC no
        $invio = new InvioPosta($this->dataMapper);
        $this->testAdapter->accodaSelect([]);
        $this->assertSame('mailpit', $invio->getCasella($this->studioPredefinito, TipoCasellaPosta::email)->host);
        $this->testAdapter->accodaSelect([]);
        $this->assertNull($invio->getCasella($this->studioPredefinito, TipoCasellaPosta::pec));

        // la casella dello studio prevale sul .env
        $this->testAdapter->accodaSelect([$this->creaCasella(TipoCasellaPosta::email)]);
        $this->assertSame('smtps.pec.aruba.it', $invio->getCasella($this->studioPredefinito, TipoCasellaPosta::email)->host);
    }

    public function testPreparaMessaggio(): void
    {
        $casella = $this->creaCasella();
        $casella->nomeMittente = 'Studio Test (PEC)';
        $messaggio = (new Messaggio('cliente@pec.it', 'Modello F24 in scadenza il 16/11/2026', "Gentile cliente,\nin allegato il modello.", 'risposte@studio.it'))
                ->allega('F24_2026-11-16.pdf', '%PDF-1.4 prova', 'application/pdf');
        $mailer = (new InvioPosta($this->dataMapper))->prepara($casella, $messaggio);

        $this->assertSame('smtps.pec.aruba.it', $mailer->Host);
        $this->assertSame(465, $mailer->Port);
        $this->assertSame(PHPMailer::ENCRYPTION_SMTPS, $mailer->SMTPSecure);
        $this->assertTrue($mailer->SMTPAuth);
        $this->assertSame('studio@pec.it', $mailer->Username);
        $this->assertSame('segreta', $mailer->Password);
        $this->assertSame('studio@pec.it', $mailer->From);
        $this->assertSame('Studio Test (PEC)', $mailer->FromName);
        $this->assertSame([['cliente@pec.it', '']], $mailer->getToAddresses());
        $this->assertCount(1, $mailer->getAttachments());
        $this->assertTrue($mailer->preSend());
        $mime = $mailer->getSentMIMEMessage();
        $this->assertStringContainsString('filename=F24_2026-11-16.pdf', $mime);
        $this->assertStringContainsString('Content-Type: application/pdf', $mime);
        $this->assertStringContainsString('X-Mailer: Telonio', $mime);
        $this->assertStringContainsString('Reply-To: risposte@studio.it', $mime);

        // senza utente niente autenticazione, STARTTLS e nessuna cifratura
        $casella = $this->creaCasella(TipoCasellaPosta::email, null);
        $casella->cifratura = CifraturaSmtp::starttls;
        $mailer = (new InvioPosta($this->dataMapper))->prepara($casella, new Messaggio('cliente@example.com', 'Oggetto', 'Testo'));
        $this->assertFalse($mailer->SMTPAuth);
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure);
        $this->assertSame('Studio Associato Test', $mailer->FromName);
        $casella->cifratura = CifraturaSmtp::nessuna;
        $mailer = (new InvioPosta($this->dataMapper))->prepara($casella, new Messaggio('cliente@example.com', 'Oggetto', 'Testo'));
        $this->assertSame('', $mailer->SMTPSecure);
        $this->assertFalse($mailer->SMTPAutoTLS);

        $this->expectException(InvioPostaException::class);
        (new InvioPosta($this->dataMapper))->prepara($casella, new Messaggio('non-valido', 'Oggetto', 'Testo'));
    }

    public function testInvia(): void
    {
        $inviati = [];
        $invio = new InvioPosta($this->dataMapper, function (PHPMailer $mailer) use (&$inviati): bool {
            $inviati[] = $mailer;
            return $mailer->preSend();
        });
        $this->testAdapter->accodaSelect([$this->creaCasella()]);
        $messageId = $invio->invia($this->studioPredefinito, TipoCasellaPosta::pec, new Messaggio('cliente@pec.it', 'Oggetto', 'Testo'));
        $this->assertCount(1, $inviati);
        $this->assertStringStartsWith('<', $messageId);

        // PEC non configurata
        $this->testAdapter->accodaSelect([]);
        try {
            $invio->invia($this->studioPredefinito, TipoCasellaPosta::pec, new Messaggio('cliente@pec.it', 'Oggetto', 'Testo'));
            $this->fail('Attesa InvioPostaException');
        } catch (InvioPostaException $errore) {
            $this->assertStringContainsString('Casella PEC dello studio non configurata', $errore->getMessage());
        }

        // errore del server SMTP
        $rifiuto = new InvioPosta($this->dataMapper, function (PHPMailer $mailer): bool {
            throw new PHPMailerException('SMTP Error: Could not authenticate.');
        });
        $this->testAdapter->accodaSelect([$this->creaCasella()]);
        try {
            $rifiuto->invia($this->studioPredefinito, TipoCasellaPosta::pec, new Messaggio('cliente@pec.it', 'Oggetto', 'Testo'));
            $this->fail('Attesa InvioPostaException');
        } catch (InvioPostaException $errore) {
            $this->assertStringContainsString('Could not authenticate', $errore->getMessage());
        }

        // invio rifiutato senza eccezione
        $fallito = new InvioPosta($this->dataMapper, fn(PHPMailer $mailer): bool => false);
        $this->testAdapter->accodaSelect([$this->creaCasella()]);
        $this->expectException(InvioPostaException::class);
        $fallito->invia($this->studioPredefinito, TipoCasellaPosta::pec, new Messaggio('cliente@pec.it', 'Oggetto', 'Testo'));
    }

    public function testCasellaPostaController(): void
    {
        $stato = $this->catturaRenderERouter();
        $utente = $this->autenticaUtente();

        // elenco: PEC dello studio, e-mail non configurata e senza .env
        $pec = $this->creaCasella();
        $this->testAdapter->accodaSelect([$utente], [], [$pec]);
        $controller = new CasellaPostaController($this->dataMapper);
        $controller->index();
        $this->assertSame('casellaPosta/index', $stato->vista);
        $this->assertNull($stato->vars['caselle'][TipoCasellaPosta::email->value]);
        $this->assertSame($pec, $stato->vars['caselle'][TipoCasellaPosta::pec->value]);
        $this->assertNull($stato->vars['casellaAmbiente']);

        // nuova casella e-mail: form con i valori proposti
        $this->testAdapter->accodaSelect([]);
        $controller->update($this->creaRequest([], [], [], 'GET'), TipoCasellaPosta::email);
        $this->assertSame('casellaPosta/form', $stato->vista);
        $this->assertSame(465, $stato->vars['formEntity']->porta);
        $this->assertFalse($stato->vars['conPassword']);

        // dati non validi
        $this->testAdapter->accodaSelect([]);
        $controller->update($this->creaRequest(['host' => 'https://smtp', 'porta' => '70000', 'cifratura' => 'S', 'indirizzo' => 'non-valido']), TipoCasellaPosta::email);
        $this->assertSame('casellaPosta/form', $stato->vista);
        $this->assertTrue($stato->vars['errors']->hostCustomMessage !== false);
        $this->assertTrue($stato->vars['errors']->indirizzoCustomMessage !== false);

        // modifica della PEC senza password: resta quella salvata
        $this->testAdapter->accodaSelect([$pec]);
        $controller->update($this->creaRequest(['host' => ' SMTPS.Pec.Aruba.it ', 'porta' => '465', 'cifratura' => 'S', 'indirizzo' => 'Studio@Pec.it', 'utente' => 'studio@pec.it', 'password' => '']), TipoCasellaPosta::pec);
        $this->assertSame('casella-posta/index', $stato->redirectUrl);
        $this->assertSame('smtps.pec.aruba.it', $pec->host);
        $this->assertSame('studio@pec.it', $pec->indirizzo);
        $this->assertSame('segreta', $pec->password);

        // senza utente la password viene tolta
        $this->testAdapter->accodaSelect([$pec]);
        $controller->update($this->creaRequest(['host' => 'smtps.pec.aruba.it', 'porta' => '465', 'cifratura' => 'S', 'indirizzo' => 'studio@pec.it', 'utente' => '', 'password' => 'nuova']), TipoCasellaPosta::pec);
        $this->assertNull($pec->utente);
        $this->assertNull($pec->password);

        // prova senza casella: messaggio d'errore, nessun invio
        $this->testAdapter->accodaSelect([]);
        $controller->prova($this->creaRequest(), TipoCasellaPosta::pec);
        $this->assertSame('casella-posta/index', $stato->redirectUrl);

        // eliminazione
        $this->testAdapter->accodaSelect([$pec]);
        $controller->delete($this->creaRequest(), TipoCasellaPosta::pec);
        $this->assertSame('casella-posta/index', $stato->redirectUrl);
    }

    public function testCasellaPostaControllerRiservatoAgliAmministratori(): void
    {
        $this->catturaRenderERouter();
        $operatore = $this->autenticaUtente($this->creaUtente($this->studioPredefinito, RuoloUtente::operatore, 2, 'operatore'));
        $this->testAdapter->accodaSelect([$operatore]);
        $controller = new CasellaPostaController($this->dataMapper);
        $this->expectException(AccessDeniedException::class);
        $controller->index();
    }
}
