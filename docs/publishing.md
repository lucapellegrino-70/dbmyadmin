# Pubblicare DbMyAdmin su Packagist

Questa guida descrive i passi necessari per mettere online il pacchetto `lucapellegrino/dbmyadmin` su [Packagist](https://packagist.org), il registry ufficiale dei pacchetti Composer.

---

## Prerequisiti

- Account su [GitHub](https://github.com)
- Account su [Packagist](https://packagist.org) (registrazione gratuita, puoi fare login con GitHub)
- Repository GitHub **pubblico** per il pacchetto
- File `LICENSE` presente nella root del repo (vedi sotto)

---

## 1. Aggiungere il file LICENSE

Packagist e GitHub mostrano la licenza del pacchetto. Crea il file `LICENSE` nella root:

```
MIT License

Copyright (c) 2026 Luca Pellegrino

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 2. Creare il repository GitHub

1. Vai su [github.com/new](https://github.com/new)
2. **Repository name:** `dbmyadmin`
3. **Visibility:** Public (obbligatorio per Packagist)
4. Non inizializzare con README o .gitignore (il repo locale li ha già)
5. Clicca **Create repository**

---

## 3. Collegare il repo locale a GitHub e fare il primo push

```bash
# Aggiungere il remote
git remote add origin https://github.com/lucapellegrino/dbmyadmin.git

# Verificare
git remote -v

# Push del branch main
git push -u origin main
```

---

## 4. Fare il primo tag di versione

Packagist usa i tag Git come versioni del pacchetto. Il primo rilascio stabile è convenzionalmente `v1.0.0`:

```bash
git tag v1.0.0
git push origin v1.0.0
```

> **Semantic versioning:** usa `vMAGGIORE.MINORE.PATCH` (es. `v1.0.0`, `v1.1.0`, `v1.0.1`).
> - MAGGIORE: breaking changes
> - MINORE: nuove funzionalità retrocompatibili
> - PATCH: bug fix

---

## 5. Registrare il pacchetto su Packagist

1. Vai su [packagist.org](https://packagist.org) e fai login
2. Clicca **Submit** in alto a destra
3. Nel campo **Repository URL**, incolla l'URL del repo GitHub:
   ```
   https://github.com/lucapellegrino/dbmyadmin
   ```
4. Clicca **Check** — Packagist legge il `composer.json` e mostra il nome rilevato (`lucapellegrino/dbmyadmin`)
5. Clicca **Submit**

Il pacchetto è ora disponibile su `https://packagist.org/packages/lucapellegrino/dbmyadmin`.

---

## 6. Configurare il webhook per aggiornamenti automatici

Ogni volta che fai push o crei un tag su GitHub, Packagist deve sapere che ci sono aggiornamenti. Il modo più affidabile è il **webhook GitHub**.

### Metodo A — tramite Packagist (consigliato)

1. Vai sulla pagina del pacchetto su Packagist
2. Clicca **Settings** (in alto a destra, visibile solo se sei l'owner)
3. Nella sezione **GitHub Hook**, clicca **Enable**
4. Packagist ti mostrerà un token da copiare

Poi su GitHub:
1. Vai su `github.com/lucapellegrino/dbmyadmin` → **Settings** → **Webhooks** → **Add webhook**
2. **Payload URL:** `https://packagist.org/api/github?username=lucapellegrino`
3. **Content type:** `application/json`
4. **Secret:** incolla il token copiato da Packagist
5. **Which events:** *Just the push event*
6. Clicca **Add webhook**

### Metodo B — GitHub App (alternativa moderna)

1. Vai su [packagist.org/profile](https://packagist.org/profile/) → **Connect with GitHub**
2. Autorizza l'app Packagist su GitHub
3. Il sync avviene automaticamente senza configurare webhook manualmente

---

## 7. Verificare che tutto funzioni

Dopo aver configurato il webhook:

```bash
# Crea un nuovo tag di test
git tag v1.0.1
git push origin v1.0.1
```

Vai su `packagist.org/packages/lucapellegrino/dbmyadmin` e verifica che la versione `v1.0.1` compaia entro pochi secondi.

Gli utenti possono ora installare il pacchetto con:

```bash
composer require lucapellegrino/dbmyadmin
```

---

## Checklist pre-release

Prima di pubblicare una versione, verifica:

- [ ] Tutti i test passano: `./vendor/bin/pest`
- [ ] `composer.json` ha la versione PHP e Laravel corrette nei `require`
- [ ] Il file `LICENSE` è presente nella root
- [ ] Il `README.md` è aggiornato
- [ ] Il tag Git corrisponde alla versione che vuoi rilasciare
- [ ] `minimum-stability` è impostato correttamente (`stable` per release ufficiali)

---

## Aggiornare minimum-stability per release stabile

Il `composer.json` attuale ha `"minimum-stability": "dev"` (utile durante lo sviluppo). Prima del primo rilascio pubblico, aggiornalo a:

```json
"minimum-stability": "stable"
```

Poi committa e tagga:

```bash
git add composer.json
git commit -m "chore: set minimum-stability to stable for v1.0.0 release"
git tag v1.0.0
git push origin main --tags
```

---

## Rilasciare versioni successive

Per ogni nuovo rilascio:

```bash
# 1. Assicurarsi di essere su main aggiornato
git checkout main
git pull origin main

# 2. Creare il tag
git tag v1.1.0

# 3. Push del tag (Packagist si aggiorna automaticamente via webhook)
git push origin v1.1.0
```

---

## Deprecare o ritirare un pacchetto

Se in futuro vuoi deprecare il pacchetto su Packagist:
1. Vai su `packagist.org/packages/lucapellegrino/dbmyadmin`
2. Clicca **Settings** → **Deprecate**
3. Puoi indicare un pacchetto sostitutivo se esiste
