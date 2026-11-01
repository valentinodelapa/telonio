<?php

namespace Contabilita\Application\Enumerations;

/**
 * Voci degli schemi di bilancio a cui si raccordano i conti: stato
 * patrimoniale (art. 2424 c.c.) e conto economico (art. 2425 c.c.) nel
 * testo modificato dal D.Lgs. 139/2015. Sono fissate dalla legge, quindi
 * un vocabolario chiuso; il valore è il percorso della voce con il
 * prefisso della sezione (A attivo, P passivo, E conto economico), es.
 * A.B.II.1 = attivo, B) II 1) terreni e fabbricati.
 */
enum VoceCee: string
{

    case attivoA = 'A.A';
    case attivoBI1 = 'A.B.I.1';
    case attivoBI2 = 'A.B.I.2';
    case attivoBI3 = 'A.B.I.3';
    case attivoBI4 = 'A.B.I.4';
    case attivoBI5 = 'A.B.I.5';
    case attivoBI6 = 'A.B.I.6';
    case attivoBI7 = 'A.B.I.7';
    case attivoBII1 = 'A.B.II.1';
    case attivoBII2 = 'A.B.II.2';
    case attivoBII3 = 'A.B.II.3';
    case attivoBII4 = 'A.B.II.4';
    case attivoBII5 = 'A.B.II.5';
    case attivoBIII1A = 'A.B.III.1.a';
    case attivoBIII1B = 'A.B.III.1.b';
    case attivoBIII1C = 'A.B.III.1.c';
    case attivoBIII1D = 'A.B.III.1.d';
    case attivoBIII1DBis = 'A.B.III.1.d-bis';
    case attivoBIII2A = 'A.B.III.2.a';
    case attivoBIII2B = 'A.B.III.2.b';
    case attivoBIII2C = 'A.B.III.2.c';
    case attivoBIII2D = 'A.B.III.2.d';
    case attivoBIII2DBis = 'A.B.III.2.d-bis';
    case attivoBIII3 = 'A.B.III.3';
    case attivoBIII4 = 'A.B.III.4';
    case attivoCI1 = 'A.C.I.1';
    case attivoCI2 = 'A.C.I.2';
    case attivoCI3 = 'A.C.I.3';
    case attivoCI4 = 'A.C.I.4';
    case attivoCI5 = 'A.C.I.5';
    case attivoCII1 = 'A.C.II.1';
    case attivoCII2 = 'A.C.II.2';
    case attivoCII3 = 'A.C.II.3';
    case attivoCII4 = 'A.C.II.4';
    case attivoCII5 = 'A.C.II.5';
    case attivoCII5Bis = 'A.C.II.5-bis';
    case attivoCII5Ter = 'A.C.II.5-ter';
    case attivoCII5Quater = 'A.C.II.5-quater';
    case attivoCIII1 = 'A.C.III.1';
    case attivoCIII2 = 'A.C.III.2';
    case attivoCIII3 = 'A.C.III.3';
    case attivoCIII3Bis = 'A.C.III.3-bis';
    case attivoCIII4 = 'A.C.III.4';
    case attivoCIII5 = 'A.C.III.5';
    case attivoCIII6 = 'A.C.III.6';
    case attivoCIII7 = 'A.C.III.7';
    case attivoCIV1 = 'A.C.IV.1';
    case attivoCIV2 = 'A.C.IV.2';
    case attivoCIV3 = 'A.C.IV.3';
    case attivoD = 'A.D';
    case passivoAI = 'P.A.I';
    case passivoAII = 'P.A.II';
    case passivoAIII = 'P.A.III';
    case passivoAIV = 'P.A.IV';
    case passivoAV = 'P.A.V';
    case passivoAVI = 'P.A.VI';
    case passivoAVII = 'P.A.VII';
    case passivoAVIII = 'P.A.VIII';
    case passivoAIX = 'P.A.IX';
    case passivoAX = 'P.A.X';
    case passivoB1 = 'P.B.1';
    case passivoB2 = 'P.B.2';
    case passivoB3 = 'P.B.3';
    case passivoB4 = 'P.B.4';
    case passivoC = 'P.C';
    case passivoD1 = 'P.D.1';
    case passivoD2 = 'P.D.2';
    case passivoD3 = 'P.D.3';
    case passivoD4 = 'P.D.4';
    case passivoD5 = 'P.D.5';
    case passivoD6 = 'P.D.6';
    case passivoD7 = 'P.D.7';
    case passivoD8 = 'P.D.8';
    case passivoD9 = 'P.D.9';
    case passivoD10 = 'P.D.10';
    case passivoD11 = 'P.D.11';
    case passivoD11Bis = 'P.D.11-bis';
    case passivoD12 = 'P.D.12';
    case passivoD13 = 'P.D.13';
    case passivoD14 = 'P.D.14';
    case passivoE = 'P.E';
    case economicoA1 = 'E.A.1';
    case economicoA2 = 'E.A.2';
    case economicoA3 = 'E.A.3';
    case economicoA4 = 'E.A.4';
    case economicoA5 = 'E.A.5';
    case economicoB6 = 'E.B.6';
    case economicoB7 = 'E.B.7';
    case economicoB8 = 'E.B.8';
    case economicoB9A = 'E.B.9.a';
    case economicoB9B = 'E.B.9.b';
    case economicoB9C = 'E.B.9.c';
    case economicoB9D = 'E.B.9.d';
    case economicoB9E = 'E.B.9.e';
    case economicoB10A = 'E.B.10.a';
    case economicoB10B = 'E.B.10.b';
    case economicoB10C = 'E.B.10.c';
    case economicoB10D = 'E.B.10.d';
    case economicoB11 = 'E.B.11';
    case economicoB12 = 'E.B.12';
    case economicoB13 = 'E.B.13';
    case economicoB14 = 'E.B.14';
    case economicoC15 = 'E.C.15';
    case economicoC16A = 'E.C.16.a';
    case economicoC16B = 'E.C.16.b';
    case economicoC16C = 'E.C.16.c';
    case economicoC16D = 'E.C.16.d';
    case economicoC17 = 'E.C.17';
    case economicoC17Bis = 'E.C.17-bis';
    case economicoD18A = 'E.D.18.a';
    case economicoD18B = 'E.D.18.b';
    case economicoD18C = 'E.D.18.c';
    case economicoD18D = 'E.D.18.d';
    case economicoD19A = 'E.D.19.a';
    case economicoD19B = 'E.D.19.b';
    case economicoD19C = 'E.D.19.c';
    case economicoD19D = 'E.D.19.d';
    case economico20 = 'E.20';

    public function getLabel(): string
    {
        return match ($this) {
            self::attivoA => 'Crediti verso soci per versamenti ancora dovuti',
            self::attivoBI1 => 'Costi di impianto e di ampliamento',
            self::attivoBI2 => 'Costi di sviluppo',
            self::attivoBI3 => 'Diritti di brevetto industriale e diritti di utilizzazione delle opere dell\'ingegno',
            self::attivoBI4 => 'Concessioni, licenze, marchi e diritti simili',
            self::attivoBI5 => 'Avviamento',
            self::attivoBI6 => 'Immobilizzazioni immateriali in corso e acconti',
            self::attivoBI7 => 'Altre immobilizzazioni immateriali',
            self::attivoBII1 => 'Terreni e fabbricati',
            self::attivoBII2 => 'Impianti e macchinario',
            self::attivoBII3 => 'Attrezzature industriali e commerciali',
            self::attivoBII4 => 'Altri beni',
            self::attivoBII5 => 'Immobilizzazioni materiali in corso e acconti',
            self::attivoBIII1A => 'Partecipazioni in imprese controllate',
            self::attivoBIII1B => 'Partecipazioni in imprese collegate',
            self::attivoBIII1C => 'Partecipazioni in imprese controllanti',
            self::attivoBIII1D => 'Partecipazioni in imprese sottoposte al controllo delle controllanti',
            self::attivoBIII1DBis => 'Partecipazioni in altre imprese',
            self::attivoBIII2A => 'Crediti immobilizzati verso imprese controllate',
            self::attivoBIII2B => 'Crediti immobilizzati verso imprese collegate',
            self::attivoBIII2C => 'Crediti immobilizzati verso controllanti',
            self::attivoBIII2D => 'Crediti immobilizzati verso imprese sottoposte al controllo delle controllanti',
            self::attivoBIII2DBis => 'Crediti immobilizzati verso altri',
            self::attivoBIII3 => 'Altri titoli immobilizzati',
            self::attivoBIII4 => 'Strumenti finanziari derivati attivi immobilizzati',
            self::attivoCI1 => 'Materie prime, sussidiarie e di consumo',
            self::attivoCI2 => 'Prodotti in corso di lavorazione e semilavorati',
            self::attivoCI3 => 'Lavori in corso su ordinazione',
            self::attivoCI4 => 'Prodotti finiti e merci',
            self::attivoCI5 => 'Acconti',
            self::attivoCII1 => 'Crediti verso clienti',
            self::attivoCII2 => 'Crediti verso imprese controllate',
            self::attivoCII3 => 'Crediti verso imprese collegate',
            self::attivoCII4 => 'Crediti verso controllanti',
            self::attivoCII5 => 'Crediti verso imprese sottoposte al controllo delle controllanti',
            self::attivoCII5Bis => 'Crediti tributari',
            self::attivoCII5Ter => 'Imposte anticipate',
            self::attivoCII5Quater => 'Crediti verso altri',
            self::attivoCIII1 => 'Partecipazioni in imprese controllate (attivo circolante)',
            self::attivoCIII2 => 'Partecipazioni in imprese collegate (attivo circolante)',
            self::attivoCIII3 => 'Partecipazioni in imprese controllanti (attivo circolante)',
            self::attivoCIII3Bis => 'Partecipazioni in imprese sottoposte al controllo delle controllanti (attivo circolante)',
            self::attivoCIII4 => 'Altre partecipazioni (attivo circolante)',
            self::attivoCIII5 => 'Strumenti finanziari derivati attivi',
            self::attivoCIII6 => 'Altri titoli (attivo circolante)',
            self::attivoCIII7 => 'Attività finanziarie per la gestione accentrata della tesoreria',
            self::attivoCIV1 => 'Depositi bancari e postali',
            self::attivoCIV2 => 'Assegni',
            self::attivoCIV3 => 'Danaro e valori in cassa',
            self::attivoD => 'Ratei e risconti attivi',
            self::passivoAI => 'Capitale',
            self::passivoAII => 'Riserva da soprapprezzo delle azioni',
            self::passivoAIII => 'Riserve di rivalutazione',
            self::passivoAIV => 'Riserva legale',
            self::passivoAV => 'Riserve statutarie',
            self::passivoAVI => 'Altre riserve',
            self::passivoAVII => 'Riserva per operazioni di copertura dei flussi finanziari attesi',
            self::passivoAVIII => 'Utili (perdite) portati a nuovo',
            self::passivoAIX => 'Utile (perdita) dell\'esercizio',
            self::passivoAX => 'Riserva negativa per azioni proprie in portafoglio',
            self::passivoB1 => 'Fondi per trattamento di quiescenza e obblighi simili',
            self::passivoB2 => 'Fondi per imposte, anche differite',
            self::passivoB3 => 'Strumenti finanziari derivati passivi',
            self::passivoB4 => 'Altri fondi per rischi e oneri',
            self::passivoC => 'Trattamento di fine rapporto di lavoro subordinato',
            self::passivoD1 => 'Obbligazioni',
            self::passivoD2 => 'Obbligazioni convertibili',
            self::passivoD3 => 'Debiti verso soci per finanziamenti',
            self::passivoD4 => 'Debiti verso banche',
            self::passivoD5 => 'Debiti verso altri finanziatori',
            self::passivoD6 => 'Acconti',
            self::passivoD7 => 'Debiti verso fornitori',
            self::passivoD8 => 'Debiti rappresentati da titoli di credito',
            self::passivoD9 => 'Debiti verso imprese controllate',
            self::passivoD10 => 'Debiti verso imprese collegate',
            self::passivoD11 => 'Debiti verso controllanti',
            self::passivoD11Bis => 'Debiti verso imprese sottoposte al controllo delle controllanti',
            self::passivoD12 => 'Debiti tributari',
            self::passivoD13 => 'Debiti verso istituti di previdenza e di sicurezza sociale',
            self::passivoD14 => 'Altri debiti',
            self::passivoE => 'Ratei e risconti passivi',
            self::economicoA1 => 'Ricavi delle vendite e delle prestazioni',
            self::economicoA2 => 'Variazioni delle rimanenze di prodotti in corso di lavorazione, semilavorati e finiti',
            self::economicoA3 => 'Variazioni dei lavori in corso su ordinazione',
            self::economicoA4 => 'Incrementi di immobilizzazioni per lavori interni',
            self::economicoA5 => 'Altri ricavi e proventi',
            self::economicoB6 => 'Costi per materie prime, sussidiarie, di consumo e di merci',
            self::economicoB7 => 'Costi per servizi',
            self::economicoB8 => 'Costi per godimento di beni di terzi',
            self::economicoB9A => 'Salari e stipendi',
            self::economicoB9B => 'Oneri sociali',
            self::economicoB9C => 'Trattamento di fine rapporto',
            self::economicoB9D => 'Trattamento di quiescenza e simili',
            self::economicoB9E => 'Altri costi del personale',
            self::economicoB10A => 'Ammortamento delle immobilizzazioni immateriali',
            self::economicoB10B => 'Ammortamento delle immobilizzazioni materiali',
            self::economicoB10C => 'Altre svalutazioni delle immobilizzazioni',
            self::economicoB10D => 'Svalutazioni dei crediti compresi nell\'attivo circolante e delle disponibilità liquide',
            self::economicoB11 => 'Variazioni delle rimanenze di materie prime, sussidiarie, di consumo e merci',
            self::economicoB12 => 'Accantonamenti per rischi',
            self::economicoB13 => 'Altri accantonamenti',
            self::economicoB14 => 'Oneri diversi di gestione',
            self::economicoC15 => 'Proventi da partecipazioni',
            self::economicoC16A => 'Proventi da crediti iscritti nelle immobilizzazioni',
            self::economicoC16B => 'Proventi da titoli iscritti nelle immobilizzazioni che non costituiscono partecipazioni',
            self::economicoC16C => 'Proventi da titoli iscritti nell\'attivo circolante che non costituiscono partecipazioni',
            self::economicoC16D => 'Proventi diversi dai precedenti',
            self::economicoC17 => 'Interessi e altri oneri finanziari',
            self::economicoC17Bis => 'Utili e perdite su cambi',
            self::economicoD18A => 'Rivalutazioni di partecipazioni',
            self::economicoD18B => 'Rivalutazioni di immobilizzazioni finanziarie che non costituiscono partecipazioni',
            self::economicoD18C => 'Rivalutazioni di titoli iscritti all\'attivo circolante che non costituiscono partecipazioni',
            self::economicoD18D => 'Rivalutazioni di strumenti finanziari derivati',
            self::economicoD19A => 'Svalutazioni di partecipazioni',
            self::economicoD19B => 'Svalutazioni di immobilizzazioni finanziarie che non costituiscono partecipazioni',
            self::economicoD19C => 'Svalutazioni di titoli iscritti all\'attivo circolante che non costituiscono partecipazioni',
            self::economicoD19D => 'Svalutazioni di strumenti finanziari derivati',
            self::economico20 => 'Imposte sul reddito dell\'esercizio, correnti, differite e anticipate',
        };
    }

    /**
     * Voce come si legge nello schema (es. "B.II.1 Terreni e fabbricati").
     */
    public function getEtichetta(): string
    {
        return substr($this->value, 2) . ' ' . $this->getLabel();
    }

    public function getSezione(): string
    {
        return match ($this->value[0]) {
            'A' => 'Stato patrimoniale - attivo',
            'P' => 'Stato patrimoniale - passivo',
            default => 'Conto economico',
        };
    }

    /**
     * La voce appartiene alla sezione del bilancio della natura del conto
     * (i conti di sistema non si raccordano).
     */
    public function isCompatibile(NaturaConto $natura): bool
    {
        return match ($natura) {
            NaturaConto::attivita => $this->value[0] === 'A',
            NaturaConto::passivita => $this->value[0] === 'P',
            NaturaConto::costo, NaturaConto::ricavo => $this->value[0] === 'E',
            NaturaConto::sistema => false,
        };
    }

    /**
     * @return array<string, self[]> voci per sezione, per le selezioni
     */
    public static function perSezione(): array
    {
        $sezioni = [];
        foreach (self::cases() as $voce) {
            $sezioni[$voce->getSezione()][] = $voce;
        }
        return $sezioni;
    }
}
