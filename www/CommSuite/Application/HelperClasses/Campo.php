<?php

namespace CommSuite\Application\HelperClasses;

use SismaFramework\Core\CustomTypes\FormFilterError;

/**
 * Campi di form Bootstrap con ripopolamento ed errori di validazione,
 * comuni a tutti i moduli della suite.
 *
 * Opzioni comuni: colonna (classe della griglia), richiesto (bool),
 * aiuto (testo sotto il campo), attributi (attributi HTML aggiuntivi),
 * messaggioErrore (messaggio per errore di validazione standard),
 * prefisso (il campo viene inviato come prefisso[nome], per più form
 * della stessa entità o con nomi in conflitto nella stessa pagina).
 */
class Campo
{

    public static string $messaggioErrore = 'Valore mancante o non valido.';

    public static function testo(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $opzioni = []): string
    {
        $input = sprintf('<input type="%s" class="form-control %s" id="%s" name="%s" value="%s" %s %s>',
                Html::e($opzioni['tipo'] ?? 'text'),
                Html::classeErrore($errori, $nome),
                self::id($nome, $opzioni),
                self::nomeHtml($nome, $opzioni),
                Html::valore($origine, $nome),
                empty($opzioni['richiesto']) ? '' : 'required',
                $opzioni['attributi'] ?? '');
        return self::contenitore($nome, $etichetta, $input, $errori, $opzioni);
    }

    public static function data(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $opzioni = []): string
    {
        return self::testo($nome, $etichetta, $origine, $errori, ['tipo' => 'date'] + $opzioni);
    }

    public static function areaTesto(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $opzioni = []): string
    {
        $input = sprintf('<textarea class="form-control %s" id="%s" name="%s" rows="%d" %s>%s</textarea>',
                Html::classeErrore($errori, $nome),
                self::id($nome, $opzioni),
                self::nomeHtml($nome, $opzioni),
                $opzioni['righe'] ?? 3,
                $opzioni['attributi'] ?? '',
                Html::valore($origine, $nome));
        return self::contenitore($nome, $etichetta, $input, $errori, $opzioni);
    }

    /**
     * @param array<string|int, string> $scelte valore => etichetta
     */
    public static function selezione(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $scelte, array $opzioni = []): string
    {
        $voci = isset($opzioni['vuota']) ? '<option value="">' . Html::e($opzioni['vuota']) . '</option>' : '';
        foreach ($scelte as $valore => $testo) {
            $voci .= sprintf('<option value="%s" %s>%s</option>', Html::e($valore), Html::selezionato($origine, $nome, $valore), Html::e($testo));
        }
        $input = sprintf('<select class="form-select %s" id="%s" name="%s" %s %s>%s</select>',
                Html::classeErrore($errori, $nome),
                self::id($nome, $opzioni),
                self::nomeHtml($nome, $opzioni),
                empty($opzioni['richiesto']) ? '' : 'required',
                $opzioni['attributi'] ?? '',
                $voci);
        return self::contenitore($nome, $etichetta, $input, $errori, $opzioni);
    }

    /**
     * @param \BackedEnum[] $casi enum con metodo getLabel()
     */
    public static function selezioneEnum(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $casi, array $opzioni = []): string
    {
        $scelte = [];
        foreach ($casi as $caso) {
            $scelte[$caso->value] = $caso->getLabel();
        }
        return self::selezione($nome, $etichetta, $origine, $errori, $scelte, $opzioni);
    }

    public static function spunta(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, array $opzioni = []): string
    {
        return sprintf('<div class="%s"><div class="form-check mt-md-4"><input class="form-check-input %s" type="checkbox" value="1" id="%s" name="%s" %s><label class="form-check-label" for="%s">%s</label>%s</div></div>',
                Html::e($opzioni['colonna'] ?? 'col-md-6'),
                Html::classeErrore($errori, $nome),
                self::id($nome, $opzioni),
                self::nomeHtml($nome, $opzioni),
                Html::spuntato($origine, $nome),
                self::id($nome, $opzioni),
                Html::e($etichetta),
                Html::messaggioErrore($errori, $nome, $opzioni['messaggioErrore'] ?? self::$messaggioErrore));
    }

    /**
     * Campo che referenzia un'entità scelta tramite ricerca (vedi suite.js).
     * Con l'opzione campoData la ricerca riceve anche il valore del campo
     * data indicato (parametro "al"), es. per filtrare per data di vigenza.
     *
     * @param string $url endpoint di ricerca che restituisce [{id, etichetta}]
     * @param string|null $testoCorrente etichetta dell'entità selezionata
     */
    public static function autocompleta(string $nome, string $etichetta, ?object $origine, FormFilterError $errori, string $url, ?string $testoCorrente, array $opzioni = []): string
    {
        $input = sprintf('<div class="autocompleta" data-url="%s"%s><input type="text" class="form-control autocompleta-testo %s" id="%s" value="%s" placeholder="%s" autocomplete="off" %s><input type="hidden" class="autocompleta-valore" name="%s" value="%s">%s</div>',
                Html::e($url),
                isset($opzioni['campoData']) ? ' data-campo-data="' . Html::e($opzioni['campoData']) . '"' : '',
                Html::classeErrore($errori, $nome),
                self::id($nome, $opzioni),
                Html::e($testoCorrente ?? ''),
                Html::e($opzioni['segnaposto'] ?? 'Digita per cercare…'),
                empty($opzioni['richiesto']) ? '' : 'required',
                self::nomeHtml($nome, $opzioni),
                Html::valore($origine, $nome),
                Html::messaggioErrore($errori, $nome, $opzioni['messaggioErrore'] ?? self::$messaggioErrore));
        return self::contenitore($nome, $etichetta, $input, null, $opzioni);
    }

    private static function nomeHtml(string $nome, array $opzioni): string
    {
        return Html::e(isset($opzioni['prefisso']) ? $opzioni['prefisso'] . '[' . $nome . ']' : $nome);
    }

    private static function id(string $nome, array $opzioni): string
    {
        return Html::e(isset($opzioni['prefisso']) ? $opzioni['prefisso'] . '_' . $nome : $nome);
    }

    private static function contenitore(string $nome, string $etichetta, string $input, ?FormFilterError $errori, array $opzioni): string
    {
        return sprintf('<div class="%s"><label for="%s" class="form-label">%s%s</label>%s%s%s</div>',
                Html::e($opzioni['colonna'] ?? 'col-md-6'),
                self::id($nome, $opzioni),
                Html::e($etichetta),
                empty($opzioni['richiesto']) ? '' : ' <span class="text-danger">*</span>',
                $input,
                $errori === null ? '' : Html::messaggioErrore($errori, $nome, $opzioni['messaggioErrore'] ?? self::$messaggioErrore),
                isset($opzioni['aiuto']) ? '<div class="form-text">' . Html::e($opzioni['aiuto']) . '</div>' : '');
    }
}
