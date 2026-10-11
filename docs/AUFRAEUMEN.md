# Aufräum-Zug – vorgemerkt

Punkte, die der Betreiber für einen eigenen Aufräum-Zug vorgesehen hat.
Nichts davon ist ein Fehler im Betrieb. Neue Punkte kommen ans Ende;
erledigte werden mit Version vermerkt, nicht gestrichen.

1. **Ältere Tags nachtragen** (vorgemerkt 11.10.2026). `v0.9.83` war der
   erste Tag im Repo. Die älteren Versionen werden über die Überschriften
   im CHANGELOG zugeordnet. Jeder Tag zeigt auf den Commit, der seine
   Überschrift trägt (REIHENREGELN 6), und geht nach github und uberspace.
2. **Unerreichbare Ausnahme in `slot_buchung_erlaubt()`** (vorgemerkt
   11.10.2026, gefunden mit v0.9.84). Die Funktion nimmt Lehrkraft und
   Verwaltung von der Obergrenze je Konto aus. Seit der tote Zweig im
   Elternweg fort ist (E24), erreicht diese Ausnahme nichts mehr. Sie steht
   da wie eine geltende Regel – dasselbe wie ein Feld, das niemand liest.
