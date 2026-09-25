# maidemde/typovigil-agent

TYPO3-Extension, die Core-Version und installierte Extensions an einen TypoVigil-Hub meldet. Der Hub gleicht sie dort gegen die offiziellen TYPO3-Sicherheitsmeldungen und Packagist ab, sodass sich der Update- und Sicherheitsstand mehrerer Installationen zentral verfolgen lässt.

## Überblick

Die Extension meldet ausschließlich nach außen. Sie öffnet keinen eigenen Endpunkt, die überwachte Installation muss also von außen nicht erreichbar sein — der Agent funktioniert hinter einer Firewall oder im internen Netz.

Gemeldet werden:

- die TYPO3-Core-Version
- jede aktive Extension mit Composer-Namen und installierter Version

Keine Inhalte, keine Nutzerdaten, keine Zugangsdaten.

Die Versionsnummern stammen aus `Composer\InstalledVersions`, sofern verfügbar, da `ext_emconf.php` häufig von dem abweicht, was Composer tatsächlich aufgelöst hat.

## Voraussetzungen

- TYPO3 13.4 oder 14.x
- PHP 8.1+
- Ein erreichbarer TypoVigil-Hub mit angelegtem Projekt

## Installation

`composer require maidemde/typovigil-agent`

## Konfiguration

Im Hub einen Datensatz *Überwachtes Projekt* anlegen. Der Zugriffstoken wird beim Speichern **einmalig** angezeigt — dann kopieren, der Hub speichert nur seinen Hash.

Ausschließlich über die Umgebung, als zwei Variablen auf der überwachten Installation:

```
TYPOVIGIL_AGENT_HUB_URL=https://zentrale.example.org
TYPOVIGIL_AGENT_TOKEN=<der kopierte Token>
```

Kein Backend-Modul, kein Eintrag in der Extension-Konfiguration: Jedes hier
überwachte Projekt läuft als Docker-Container, dessen `config/system` bei
jedem Deploy frisch aus dem Image gebaut wird — ein dort eingetragener Wert
wäre nach dem nächsten Deploy kommentarlos wieder weg. Ein solches Modul gab
es früher; es wurde entfernt, weil es eine funktionierende Eingabe zeigte, die
beim nächsten Deploy trotzdem verschwand.

Zum Schluss unter **System → Planer** die Aufgabe *TypoVigil: send report* anlegen und täglich ausführen lassen.

Der Planer selbst braucht einen laufenden Cronjob auf dem Server, sonst wird die Aufgabe nie ausgeführt. Ob einer läuft, zeigt im Planer-Modul der Button *Setup check*. In Container-Umgebungen ist er oft nicht vorhanden und muss eingerichtet werden:

```
* * * * * cd /var/www/html && php vendor/bin/typo3 scheduler:run
```

Die Hub-URL muss `https` verwenden (`http://localhost` ist für die lokale Entwicklung erlaubt). Über einfaches http verweigert der Agent den Versand, da der Bearer-Token sonst lesbar übertragen würde.

## Zeitpunkt der Übertragung

- einmal täglich über die Planer-Aufgabe
- unmittelbar nach dem Aktivieren oder Deaktivieren einer Extension, damit der Hub nach einem Update nicht bis zu einen Tag lang einen veralteten Stand zeigt
- direkt nach einem Deploy, wenn der Docker-Entrypoint `typovigil-agent:report` aufruft — siehe unten. Nötig, weil ein reiner Versions-Bump eines bereits installierten Composer-Pakets (genau das, was ein von TypoVigil beauftragtes Update ist) weder aktiviert noch deaktiviert und daher keinen der beiden anderen Auslöser trifft

Der änderungsgesteuerte Versand (Aktivierung/Deaktivierung) entfällt, wenn sich tatsächlich nichts geändert hat. Die Markierung „bereits gesendet“ wird erst geschrieben, nachdem der Hub den Empfang bestätigt hat — ein fehlgeschlagener Versand wird also erneut versucht statt stillschweigend vergessen. `typovigil-agent:report` und die Planer-Aufgabe melden dagegen unbedingt, auch ohne Änderung — nur so zeigt der Hub verlässlich, ob eine Installation überhaupt noch erreichbar ist.

### Sofort nach dem Deploy melden

Im Docker-Entrypoint der überwachten Seite, direkt nach `extension:setup`:

```bash
php vendor/bin/typo3 typovigil-agent:report || true
```

`|| true` ist Pflicht: ein Backend, das gerade erst hochfährt oder noch keine Netzwerkverbindung hat, darf den Deploy nicht zum Scheitern bringen.

## Technische Details

- **PackageCollector** — sammelt Core-Version und aktive Extensions, bevorzugt die von Composer aufgelösten Versionen aus `Composer\InstalledVersions`
- **ReportSender** — sendet den Bericht per POST an `<hubUrl>/typovigil/report`, authentifiziert per Bearer-Token; merkt sich einen Hash des letzten Berichts in der `Registry`, um unveränderte Berichte zu überspringen
- **SendReportTask** — die Planer-Aufgabe für den täglichen Versand
- **SendReportCommand** — `typovigil-agent:report`, für den Aufruf aus dem Deploy-Entrypoint
- **PackageChangeListener** — stößt den Versand an, sobald sich der Extension-Bestand ändert

## Fehlersuche

`php vendor/bin/typo3 typovigil-agent:report` meldet das Ergebnis direkt auf der Kommandozeile. Schlägt ein Versand fehl, stehen die Details im TYPO3-Log (`var/log/typo3_*.log`), protokolliert von `Maidemde.TypovigilAgent.Service.ReportSender`:

| Meldung | Ursache |
| --- | --- |
| `hubUrl or token not configured` | `TYPOVIGIL_AGENT_HUB_URL`/`TYPOVIGIL_AGENT_TOKEN` fehlen in der Umgebung |
| `hubUrl must use https` | Einfaches http wird abgelehnt, https verwenden |
| `401 Unauthorized` | Der Token passt nicht zum Projekt im Hub |
| `report failed` | Hub nicht erreichbar — URL und Netzwerk prüfen |

## Lizenz

GPL-2.0-or-later
