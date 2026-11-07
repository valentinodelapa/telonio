<?php

namespace Telonio\Application\Services\Posta;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Telonio\Application\Entities\CasellaPosta;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Enumerations\CifraturaSmtp;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use Telonio\Application\Models\CasellaPostaModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Invio di un messaggio dalla casella dello studio scelta (e-mail ordinaria
 * o PEC) con PHPMailer su SMTP.
 *
 * La casella è quella configurata dallo studio (CasellaPosta); per l'e-mail
 * ordinaria, se lo studio non l'ha configurata, valgono i parametri MAIL_*
 * del .env dell'installazione. La PEC non ha alternative: il messaggio ha
 * valore di PEC solo se parte dalla casella PEC dello studio.
 *
 * La consegna al server è delegata a $trasporto (di default
 * PHPMailer::send), sostituibile nei test.
 */
class InvioPosta
{

    private const TIMEOUT = 30;

    /** @var \Closure(PHPMailer): bool */
    private \Closure $trasporto;

    /**
     * @param (\Closure(PHPMailer): bool)|null $trasporto
     */
    public function __construct(private DataMapper $dataMapper = new DataMapper(), ?\Closure $trasporto = null)
    {
        $this->trasporto = $trasporto ?? fn(PHPMailer $mailer): bool => $mailer->send();
    }

    /**
     * Casella da usare per il tipo scelto, null se non configurata.
     */
    public function getCasella(Studio $studio, TipoCasellaPosta $tipo): ?CasellaPosta
    {
        $casella = (new CasellaPostaModel($this->dataMapper))->getCasella($studio, $tipo);
        if (($casella === null) && ($tipo === TipoCasellaPosta::email)) {
            $casella = self::getCasellaAmbiente($studio, $this->dataMapper);
        }
        return $casella;
    }

    /**
     * Casella e-mail dell'installazione dai parametri MAIL_* del .env (non
     * salvata), null se MAIL_HOST o MAIL_FROM_ADDRESS mancano. Il mittente
     * ha il nome dello studio.
     */
    public static function getCasellaAmbiente(Studio $studio, DataMapper $dataMapper = new DataMapper()): ?CasellaPosta
    {
        $host = trim((string) getenv('MAIL_HOST'));
        $indirizzo = trim((string) getenv('MAIL_FROM_ADDRESS'));
        if (($host === '') || ($indirizzo === '')) {
            return null;
        }
        $casella = new CasellaPosta($dataMapper);
        $casella->studio = $studio;
        $casella->tipoCasella = TipoCasellaPosta::email;
        $casella->host = $host;
        $casella->cifratura = CifraturaSmtp::daAmbiente((string) getenv('MAIL_ENCRYPTION'));
        $porta = (int) getenv('MAIL_PORT');
        $casella->porta = $porta > 0 ? $porta : $casella->cifratura->getPortaPredefinita();
        $utente = trim((string) getenv('MAIL_USERNAME'));
        $casella->utente = $utente === '' ? null : $utente;
        $password = (string) getenv('MAIL_PASSWORD');
        $casella->password = $password === '' ? null : $password;
        $casella->indirizzo = $indirizzo;
        return $casella;
    }

    /**
     * @return string Message-ID del messaggio inviato
     * @throws InvioPostaException
     */
    public function invia(Studio $studio, TipoCasellaPosta $tipo, Messaggio $messaggio): string
    {
        $casella = $this->getCasella($studio, $tipo);
        if ($casella === null) {
            throw new InvioPostaException(sprintf('Casella %s dello studio non configurata: indicarla in Studio > Posta in uscita.', $tipo->getLabel()));
        }
        $mailer = $this->prepara($casella, $messaggio);
        try {
            if (($this->trasporto)($mailer) === false) {
                throw new InvioPostaException('Invio non riuscito: ' . $mailer->ErrorInfo);
            }
        } catch (PHPMailerException $errore) {
            throw new InvioPostaException('Invio non riuscito: ' . ($mailer->ErrorInfo !== '' ? $mailer->ErrorInfo : $errore->getMessage()), 0, $errore);
        }
        return $mailer->getLastMessageID();
    }

    /**
     * Messaggio pronto per l'invio dalla casella.
     *
     * @throws InvioPostaException destinatario o mittente non validi
     */
    public function prepara(CasellaPosta $casella, Messaggio $messaggio): PHPMailer
    {
        if (filter_var($messaggio->destinatario, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvioPostaException('Indirizzo del destinatario non valido: ' . $messaggio->destinatario);
        }
        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = $casella->host;
            $mailer->Port = $casella->porta;
            $mailer->Timeout = self::TIMEOUT;
            $mailer->SMTPSecure = match ($casella->cifratura) {
                CifraturaSmtp::sslTls => PHPMailer::ENCRYPTION_SMTPS,
                CifraturaSmtp::starttls => PHPMailer::ENCRYPTION_STARTTLS,
                CifraturaSmtp::nessuna => '',
            };
            $mailer->SMTPAutoTLS = $casella->cifratura !== CifraturaSmtp::nessuna;
            $mailer->SMTPAuth = $casella->utente !== null;
            if ($mailer->SMTPAuth) {
                $mailer->Username = $casella->utente;
                $mailer->Password = (string) $casella->password;
            }
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mailer->XMailer = 'Telonio';
            $mailer->setFrom($casella->indirizzo, $casella->getMittente(), false);
            $mailer->addAddress($messaggio->destinatario);
            if ($messaggio->rispondiA !== null) {
                $mailer->addReplyTo($messaggio->rispondiA);
            }
            $mailer->isHTML(false);
            $mailer->Subject = $messaggio->oggetto;
            $mailer->Body = $messaggio->testo;
            foreach ($messaggio->getAllegati() as $allegato) {
                $mailer->addStringAttachment($allegato['contenuto'], $allegato['nome'], PHPMailer::ENCODING_BASE64, $allegato['tipo']);
            }
        } catch (PHPMailerException $errore) {
            throw new InvioPostaException('Messaggio non valido: ' . $errore->getMessage(), 0, $errore);
        }
        return $mailer;
    }
}
