<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German language strings for the Wunderbyte AI provider.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action_generate_agent_reply'] = 'Agent-Antwort generieren';
$string['action_generate_agent_reply_desc'] = 'Erzeugt die abschließende Antwort, die der Agent ausgibt.';
$string['action_generate_agent_reply_help'] = 'Formuliert die abschließende Antwort des Assistenten aus den gesammelten Ergebnissen der Aufgabe.';
$string['action_generate_content'] = 'Inhalte generieren';
$string['action_generate_content_desc'] = 'Erstellt Kurskapitel, Gliederungen und Quizfragen mit dem Inhaltsmodell.';
$string['action_generate_content_help'] = 'Schreibt die Texte, mit denen der Agent Kurse und Fragensammlungen füllt; wird nur verwendet, solange die erweiterte Inhaltserstellung des Agenten eingeschaltet ist.';
$string['action_generate_embeddings'] = 'Embeddings generieren';
$string['action_generate_embeddings_desc'] = 'Erzeugt Embedding-Vektoren für die Einträge des Aufgabenkatalogs und für Nutzeranfragen.';
$string['action_generate_embeddings_help'] = 'Erstellt eine Vektordarstellung des übergebenen Textes.';
$string['action_generate_embeddings_instruction'] = 'Gib ein Vektor-Embedding für den übergebenen Text zurück.';
$string['action_inspect_image'] = 'Bild prüfen';
$string['action_inspect_image_desc'] = 'Beantwortet eine Frage zu einem oder mehreren Bildern mit einem Modell, das Bilder lesen kann.';
$string['action_inspect_image_help'] = 'Damit prüft der Agent, ob ein Bild zeigt, was es zeigen soll, bevor er es vorschlägt oder einfügt; das Modell dieser Aktion muss Bilder als Eingabe annehmen.';
$string['action_planner_construct'] = 'Planer: Parameter aufbauen';
$string['action_planner_construct_desc'] = 'Baut aus der Anfrage die Parameter der ausgewählten Aufgabe auf.';
$string['action_planner_construct_help'] = 'Füllt die Eingabe der Aufgabe, die der Planer ausgewählt hat, und gibt sie als strukturierte Entscheidung aus.';
$string['action_planner_decide'] = 'Planer: Aufgabe auswählen';
$string['action_planner_decide_desc'] = 'Wählt aus den Kandidaten die passende Aufgabe aus und erzeugt die Routing-Entscheidung.';
$string['action_planner_decide_help'] = 'Wählt den am besten passenden Aufgabenkandidaten aus und gibt eine strukturierte Entscheidung aus.';
$string['apikey'] = 'API-Schlüssel';
$string['apikey_help'] = 'API-Schlüssel für den OpenAI-kompatiblen Endpunkt von Wunderbyte.';
$string['dimensions'] = 'Embedding-Dimensionen';
$string['endpoint'] = 'API-Endpunkt';
$string['error:busy'] = 'Der KI-Dienst ist im Moment stark ausgelastet. Bitte versuchen Sie es in ein paar Minuten noch einmal.';
$string['error:imagenotstored'] = 'Das Bild, das der KI-Dienst gesendet hat, konnte nicht gespeichert werden. Möglicherweise hat es ein Format, das diese Website nicht verarbeiten kann.';
$string['error:noimage'] = 'Der KI-Dienst hat ohne Bild geantwortet.';
$string['extraparams'] = 'Zusätzliche Parameter';
$string['extraparams_help'] = 'Optionale zusätzliche Parameter der Anfrage als JSON; sie werden unverändert in die Anfrage an das Modell übernommen. Damit lässt sich zum Beispiel eine feste Obergrenze für die Ausgabe setzen: {"max_tokens": 500}. Neuere Modelle verlangen stattdessen unter Umständen {"max_completion_tokens": 500}.';
$string['imageextraparams_help'] = 'Optionale zusätzliche Parameter der Anfrage als JSON; sie werden zuletzt in die Anfrage übernommen. Sie ersetzen, was standardmäßig gesendet wird (die Größe zum gewählten Seitenverhältnis: 1024x1024, 1536x1024 oder 1024x1536; die Qualität: medium oder high). Der Wert null lässt einen Parameter weg, zum Beispiel {"quality": null}.';
$string['imagemodel_help'] = 'Der Name eines Modells, das Bilder erzeugt, so wie der Endpunkt es kennt. Es gibt keine Vorgabe: Tragen Sie ein Modell ein, das Ihr Endpunkt anbietet, und schalten Sie die Aktion dann ein.';
$string['invalidjson'] = 'Ungültiges JSON';
$string['model'] = 'Modell';
$string['pluginname'] = 'Wunderbyte KI-Provider';
$string['privacy:metadata:aiprovider_wunderbyte:action'] = 'Der Name der KI-Aktion, die die Anfrage sendet, damit der Dienst die Einstellungen dieser Aktion anwenden kann.';
$string['privacy:metadata:aiprovider_wunderbyte:externalpurpose'] = 'Diese Informationen werden an den KI-Dienst von Wunderbyte (LiteLLM-Proxy) gesendet, damit eine Antwort erzeugt werden kann. Anfrage und Antwort werden zur Abrechnung und Nachvollziehbarkeit auf der Moodle-Website protokolliert; die URL der Website wird mit jeder Anfrage übertragen, damit Wunderbyte den API-Schlüssel den Websites zuordnen kann, die ihn verwenden (Schlüsselverwaltung und Support).';
$string['privacy:metadata:aiprovider_wunderbyte:imageoptions'] = 'Bei der Aktion „Bild generieren“: die Anzahl der Bilder sowie die gewünschte Größe und Qualität.';
$string['privacy:metadata:aiprovider_wunderbyte:images'] = 'Die Bilder, die mit einer Frage zu ihnen gesendet werden (Aktion „Bild prüfen“).';
$string['privacy:metadata:aiprovider_wunderbyte:model'] = 'Das Modell, mit dem die Antwort erzeugt wird.';
$string['privacy:metadata:aiprovider_wunderbyte:moodle_site'] = 'Die URL dieser Moodle-Website (wwwroot); sie wird als Metadatum der Anfrage übertragen, damit der API-Schlüssel den Websites zugeordnet werden kann, die ihn tatsächlich verwenden.';
$string['privacy:metadata:aiprovider_wunderbyte:prompttext'] = 'Der Text, aus dem die Antwort erzeugt wird: der Prompt der Anfrage oder der Text, für den ein Embedding erstellt wird. Er kann Text enthalten, den eine Person eingegeben hat.';
$string['privacy:metadata:aiprovider_wunderbyte:userid'] = 'Ein Hash aus der Kennung der Website und der ID der Person, die die Anfrage ausgelöst hat. Damit kann der Dienst die Anfragen verschiedener Personen unterscheiden, ohne einen Namen oder eine E-Mail-Adresse zu erhalten.';
$string['privacy:metadata:field:completiontoken'] = 'Die Anzahl der Token der Antwort.';
$string['privacy:metadata:field:dimensions'] = 'Die Anzahl der Dimensionen des Embeddings.';
$string['privacy:metadata:field:embedding'] = 'Das Embedding, das der Dienst für den Text erstellt hat, eine Liste von Zahlen.';
$string['privacy:metadata:field:fingerprint'] = 'Der Fingerabdruck der Modellkonfiguration, wie der Dienst ihn gemeldet hat.';
$string['privacy:metadata:field:finishreason'] = 'Der Grund, den das Modell für das Ende seiner Antwort angegeben hat.';
$string['privacy:metadata:field:generatedcontent'] = 'Die Antwort des Modells.';
$string['privacy:metadata:field:inputtext'] = 'Der Text, für den ein Embedding erstellt wurde. Er kann Text enthalten, den eine Person eingegeben hat.';
$string['privacy:metadata:field:model'] = 'Das Modell, das das Embedding erstellt hat.';
$string['privacy:metadata:field:prompt'] = 'Der Prompt, der an das Modell gesendet wurde. Er kann Text enthalten, den eine Person eingegeben hat, und Daten der Website, um die es in der Anfrage geht.';
$string['privacy:metadata:field:prompttokens'] = 'Die Anzahl der Token der Anfrage.';
$string['privacy:metadata:field:responseid'] = 'Die Kennung, die der Dienst seiner Antwort gegeben hat.';
$string['privacy:metadata:table:generate_agent_reply'] = 'Die Anfragen der Aktion „Agent-Antwort generieren“ und die Antworten darauf. Das KI-Subsystem von Moodle hält fest, welche Person eine Anfrage gesendet hat.';
$string['privacy:metadata:table:generate_content'] = 'Die Anfragen der Aktion „Inhalte generieren“ und die Antworten darauf. Das KI-Subsystem von Moodle hält fest, welche Person eine Anfrage gesendet hat.';
$string['privacy:metadata:table:generate_embeddings'] = 'Die Texte der Aktion „Embeddings generieren“ und die dafür erstellten Embeddings. Das KI-Subsystem von Moodle hält fest, welche Person eine Anfrage gesendet hat.';
$string['privacy:metadata:table:inspect_image'] = 'Die Fragen der Aktion „Bild prüfen“ und die Antworten darauf; die Bilder werden nicht gespeichert. Das KI-Subsystem von Moodle hält fest, welche Person eine Anfrage gesendet hat.';
$string['privacy:metadata:table:planner_decide'] = 'Die Anfragen der Aktionen „Planer: Aufgabe auswählen“ und „Planer: Parameter aufbauen“ und die Antworten darauf. Das KI-Subsystem von Moodle hält fest, welche Person eine Anfrage gesendet hat.';
$string['systeminstruction'] = 'Systemanweisung';
$string['usage_buy_new'] = 'Neues KI-Guthaben holen';
$string['usage_days_left'] = 'noch {$a} Tage';
$string['usage_detail_badresponse'] = 'Die Antwort auf die Verbrauchsabfrage konnte nicht gelesen werden.';
$string['usage_detail_httpstatus'] = 'Die Verbrauchsabfrage wurde mit dem HTTP-Status {$a} beantwortet.';
$string['usage_detail_noendpoint'] = 'Keine Aktion dieser Provider-Instanz hat einen Endpunkt, deshalb ist die Adresse für die Verbrauchsabfrage unbekannt.';
$string['usage_detail_requestfailed'] = 'Die Verbrauchsabfrage ist fehlgeschlagen: {$a}';
$string['usage_expired'] = 'Abgelaufen';
$string['usage_expired_on'] = 'Schlüssel abgelaufen am {$a}';
$string['usage_expires'] = 'Schlüssel läuft ab am {$a}';
$string['usage_heading'] = 'KI-Guthaben';
$string['usage_left'] = '{$a} übrig';
$string['usage_percent_remaining'] = '{$a}% übrig';
$string['usage_percent_used'] = '{$a}% verbraucht';
$string['usage_remaining'] = '{$a->remaining} von {$a->total} übrig';
$string['usage_reset_days'] = 'Wird in {$a} Tagen zurückgesetzt';
$string['usage_reset_today'] = 'Wird heute zurückgesetzt';
$string['usage_reset_tomorrow'] = 'Wird morgen zurückgesetzt';
$string['usage_spent'] = '{$a->spend} verbraucht';
$string['usage_unavailable'] = 'Angaben zum Verbrauch sind im Moment nicht verfügbar.';
$string['usage_unconfigured'] = 'Tragen Sie einen API-Schlüssel ein, um Ihr verbleibendes KI-Guthaben zu sehen.';
$string['usage_unlimited'] = 'Unbegrenzt';
$string['usage_unlimited_detail'] = 'Für diesen Schlüssel gilt kein Ausgabenlimit.';
$string['wunderbyte:viewusage'] = 'Verbrauch des KI-Guthabens für den Wunderbyte-Provider ansehen';
