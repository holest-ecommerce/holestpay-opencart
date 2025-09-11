# HolestPay Payment Gateway za OpenCart 3 & 4

Sveobuhvatan modul za integraciju plaćanja i dostave za OpenCart koji pruža punu HolestPay funkcionalnost uključujući više načina plaćanja, pretplate, kalkulaciju troškova dostave i upravljanje porudžbinama.

> **🔧 Kompatibilnost sa OpenCart 3**: Ovaj ekstenzija uključuje posebne kompatibilne fajlove za OpenCart 3.x. Ako koristite OpenCart 3 i ekstenzija se ne pojavljuje u Extensions > Payments, pogledajte [Vodič za instalaciju OpenCart 3](#opencart-3-instalacija) ispod.

## Funkcionalnosti

### Obrada plaćanja
- **Više načina plaćanja**: Podrška za sve HolestPay načine plaćanja kao zasebne opcije
- **Podrška za pretplate**: Ponavljajuća plaćanja sa MIT (Merchant Initiated Transactions) i COF (Card on File)
- **Upravljanje Vault tokenima**: Čuvanje i ponovno korišćenje načina plaćanja za brže finalizovanje kupovine
- **Potpisivanje zahteva**: Sigurna komunikacija sa digitalnim potpisima
- **Obrađivanje u realnom vremenu**: Trenutna obrada plaćanja i ažuriranje statusa

### Integracija dostave
- **Više načina dostave**: HolestPay načini dostave sa kalkulacijom troškova u realnom vremenu
- **Dinamičko cenovništvo**: Kalkulacija troškova na osnovu težine, dimenzija i destinacije
- **Dostava po zonama**: Različite cene za domaću i međunarodnu dostavu

### Admin funkcionalnosti
- **Upravljanje porudžbinama**: Kompletno HolestPay komandno sučelje u detaljima porudžbine
- **Upravljanje konfiguracijom**: Automatska sinhronizacija konfiguracije preko webhook-ova
- **Praćenje statusa**: Praćenje statusa porudžbina i plaćanja u realnom vremenu
- **Webhook integracija**: Automatska ažuriranja porudžbina iz HolestPay sistema

### Tehničke funkcionalnosti
- **Kompatibilnost sa OpenCart 3 & 4**: Jedan kod radi sa oba verzije koristeći automatsko prepoznavanje verzije
- **Multi-okruženje**: Podrška za Sandbox i Production okruženje
- **Webhook obrada**: Rukovanje konfiguracijom, ažuriranjima porudžbina i rezultatima plaćanja
- **Integracija baze podataka**: Prilagođene tabele za čuvanje HolestPay podataka
- **JavaScript integracija**: Admin i frontend JavaScript objekti
- **Automatska kompatibilnost**: Prepoznaje verziju OpenCart-a i učitava odgovarajuće kontroler fajlove

## Instalacija

### Preduslovi
- OpenCart 3.0.0.0 ili više (podržane su OpenCart 3.x i 4.x verzije)
- PHP 7.4 ili više
- Potrebne PHP ekstenzije: curl, json, openssl
- SSL sertifikat (preporučeno za produkciju)

### Za OpenCart 4.x

#### Automatska instalacija (preporučeno)
1. Preuzmite `holestpay.ocmod.zip` paket
2. Idite na Extensions → Installer u vašem OpenCart admin-u
3. Upload-ujte zip fajl
4. Idite na Extensions → Extensions → Payments
5. Pronađite "HolestPay Payment Gateway" i kliknite Install
6. Kliknite Edit da konfigurišete modul

#### Manualna instalacija
1. Ekstraktujte zip fajl
2. Upload-ujte fajlove u vaš OpenCart direktorijum zadržavajući strukturu foldera
3. Idite na Extensions → Extensions → Payments
4. Pronađite "HolestPay Payment Gateway" i kliknite Install

## OpenCart 3 Instalacija

> **⚠️ Važno**: OpenCart 3 koristi drugačiju strukturu kontrolera od OpenCart 4. Ova ekstenzija uključuje posebne kompatibilne fajlove za rad sa oba verzije.

### Zašto OpenCart 3 treba posebne fajlove?

OpenCart 3 i 4 imaju različite strukture kontrolera:
- **OpenCart 3**: Koristi strukturu baziranu na klasama (`ControllerPaymentHolestpay`)
- **OpenCart 4**: Koristi strukturu baziranu na namespace-ovima (`Opencart\Admin\Controller\Payment\Holestpay`)

Ova ekstenzija uključuje oba verzije i automatski prepoznaje koji da koristi.

### Brza instalacija (preporučeno)

1. **Preuzmite** `holestpay.ocmod.zip` paket
2. **Upload-ujte** sve fajlove u vaš OpenCart direktorijum
3. **Pokrenite skript za kompatibilnost**:
   ```bash
   cd /putanja/do/vas/opencart/
   php fix_opencart3_compatibility.php
   ```
4. **Idite u admin panel** → Extensions → Extensions → Payments
5. **Pronađite "HolestPay"** i kliknite Install
6. **Kliknite Edit** da konfigurišete vaše postavke

### Manualna instalacija

Ako automatski skript ne radi:

1. **Ekstraktujte** zip fajl
2. **Upload-ujte** sve fajlove u vaš OpenCart direktorijum
3. **Kopirajte kompatibilne fajlove za OpenCart 3**:
   ```bash
   # Kopirajte admin fajlove
   cp admin/controller/payment/holestpay_opencart3.php admin/controller/payment/holestpay.php
   cp admin/model/payment/holestpay_opencart3.php admin/model/payment/holestpay.php
   
   # Kopirajte catalog fajlove
   cp catalog/controller/payment/holestpay_opencart3.php catalog/controller/payment/holestpay.php
   ```
4. **Idite u admin panel** → Extensions → Extensions → Payments
5. **Pronađite "HolestPay"** i kliknite Install

### Rešavanje problema

**Problem**: Ekstenzija se ne pojavljuje u Extensions > Payments

**Rešenja**:
1. **Proverite dozvole fajlova**:
   ```bash
   chmod 755 admin/controller/payment/
   chmod 644 admin/controller/payment/holestpay.php
   chmod 755 admin/model/payment/
   chmod 644 admin/model/payment/holestpay.php
   chmod 755 catalog/controller/payment/
   chmod 644 catalog/controller/payment/holestpay.php
   ```

2. **Očistite OpenCart keš**:
   - Idite na Dashboard → Gear Icon → Developer Settings
   - Kliknite "Refresh" za oba Theme i SASS keš-a

3. **Proverite error log-ove**:
   - Idite na System → Maintenance → Error Logs
   - Tražite bilo kakve HolestPay povezane greške

4. **Proverite da fajlovi postoje**:
   ```bash
   ls -la admin/controller/payment/holestpay.php
   ls -la admin/model/payment/holestpay.php
   ls -la catalog/controller/payment/holestpay.php
   ```

### Fajlovi uključeni za OpenCart 3

- `admin/controller/payment/holestpay_opencart3.php` - OpenCart 3 admin kontroler
- `admin/model/payment/holestpay_opencart3.php` - OpenCart 3 admin model
- `catalog/controller/payment/holestpay_opencart3.php` - OpenCart 3 catalog kontroler
- `fix_opencart3_compatibility.php` - Automatski skript za kompatibilnost
- `OPENCART3_INSTALLATION.md` - Detaljni vodič za instalaciju

Za detaljno rešavanje problema, pogledajte [OPENCART3_INSTALLATION.md](OPENCART3_INSTALLATION.md).

## Konfiguracija

### Potrebne postavke
1. **Okruženje**: Izaberite Sandbox za testiranje ili Production za live transakcije
2. **Merchant Site UID**: Vaš jedinstveni identifikator koji je dao HolestPay
3. **Secret Key**: Vaš tajni ključ za sigurnu komunikaciju

### Opcione postavke
1. **Naziv**: Naziv koji se prikazuje na checkout stranici
2. **Opis**: Opis koji se prikazuje na checkout stranici
3. **Sortiranje**: Redosled prikaza u listi načina plaćanja
4. **Geo zona**: Ograničite dostupnost na određene geografske zone
5. **Status porudžbine**: Status porudžbine nakon uspešnog plaćanja
6. **Status neuspešnog plaćanja**: Status porudžbine nakon neuspešnog plaćanja

## Webhook konfiguracija

### Postavljanje webhook-a u HolestPay panelu

1. **Idite u HolestPay admin panel**
2. **Idite na Settings → Webhooks**
3. **Dodajte novi webhook** sa sledećim URL-om:
   ```
   https://yourdomain.com/index.php?route=extension/holestpay/payment/holestpay
   ```
4. **Izaberite topike**:
   - `posconfig-updated` - za ažuriranje konfiguracije
   - `orderupdate` - za ažuriranje porudžbina
   - `payresult` - za rezultate plaćanja

### Testiranje webhook-a

```bash
# Test webhook endpoint-a
curl -X POST https://yourdomain.com/index.php?route=extension/holestpay/payment/holestpay \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook"}'
```

## API integracija

### HolestPay API pozivi

```php
// Primer API poziva
$hpay_request = array(
    "merchant_site_uid" => "your-merchant-site-uid",
    "order_uid" => "12345",
    "order_name" => "#12345",
    "order_amount" => 199.99,
    "order_currency" => "USD",
    "order_items" => array(
        array(
            "name" => "Product Name",
            "quantity" => 1,
            "price" => 199.99,
            "total" => 199.99
        )
    ),
    "order_billing" => array(
        "email" => "customer@example.com",
        "first_name" => "John",
        "last_name" => "Doe"
    ),
    "verificationhash" => "generated_signature"
);
```

### Webhook obrada

```php
// Primer webhook obrade
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['topic'])) {
        switch ($input['topic']) {
            case 'posconfig-updated':
                // Ažuriraj konfiguraciju
                break;
            case 'orderupdate':
                // Ažuriraj porudžbinu
                break;
            case 'payresult':
                // Obrađuj rezultat plaćanja
                break;
        }
    }
}
```

## Rešavanje problema

### Problemi specifični za OpenCart 3

**P: Ekstenzija se ne pojavljuje u Extensions > Payments na OpenCart 3**
O: Ovo je najčešći problem. Rešenje je kopiranje kompatibilnih fajlova za OpenCart 3:
```bash
# Pokrenite automatski skript za popravku
php fix_opencart3_compatibility.php

# ILI ručno kopirajte fajlove
cp admin/controller/payment/holestpay_opencart3.php admin/controller/payment/holestpay.php
cp admin/model/payment/holestpay_opencart3.php admin/model/payment/holestpay.php
cp catalog/controller/payment/holestpay_opencart3.php catalog/controller/payment/holestpay.php
```

**P: "Class not found" greške na OpenCart 3**
O: Ovo znači da se koriste pogrešni kontroler fajlovi. Proverite da ste kopirali `*_opencart3.php` fajlove na standardne lokacije.

**P: Ekstenzija se instalira ali ne radi pravilno na OpenCart 3**
O: Proverite da su sva tri fajla kopirana:
- `admin/controller/payment/holestpay.php` (kopiran iz holestpay_opencart3.php)
- `admin/model/payment/holestpay.php` (kopiran iz holestpay_opencart3.php)
- `catalog/controller/payment/holestpay.php` (kopiran iz holestpay_opencart3.php)

### Opšti problemi

**Načini plaćanja se ne prikazuju**
- Proverite da li je webhook konfiguracija ispravna
- Proverite da li je HolestPay poslao konfiguraciju
- Proverite tabelu baze podataka `holestpay_payment_methods`

**Troškovi dostave se ne kalkulišu**
- Proverite da li su načini dostave konfigurisani u HolestPay-u
- Proverite da li su težina i dimenzije korpe postavljene
- Proverite konfiguraciju načina dostave

**Webhook ne prima podatke**
- Proverite da li je webhook URL dostupan
- Proverite server log-ove za greške
- Potvrdite da potvrđivanje potpisa radi

## Debugging

Omogućite debug logovanje dodavanjem u vaše config fajlove:
```php
define('HOLESTPAY_DEBUG', true);
```

Proverite log-ove u:
- `system/storage/logs/holestpay.log`
- Server error log-ovi
- Browser developer console

## Brza referenca

### Korisnici OpenCart 3
- **Problem**: Ekstenzija nije vidljiva u Extensions > Payments
- **Rešenje**: Pokrenite `php fix_opencart3_compatibility.php`
- **Fajlovi**: Koristite `*_opencart3.php` fajlove za OpenCart 3

### Korisnici OpenCart 4
- **Instalacija**: Standardni ekstenzija installer radi
- **Fajlovi**: Automatski koristi kontrolere bazirane na namespace-ovima

### Struktura fajlova
```
holestpay-opencart/
├── admin/
│   ├── controller/payment/
│   │   ├── holestpay.php              # OpenCart 4 kontroler
│   │   ├── holestpay_opencart3.php    # OpenCart 3 kontroler
│   │   └── holestpay_compatibility.php # Prepoznavanje verzije
│   └── model/payment/
│       ├── holestpay.php              # OpenCart 4 model
│       └── holestpay_opencart3.php    # OpenCart 3 model
├── catalog/
│   └── controller/payment/
│       ├── holestpay.php              # OpenCart 4 kontroler
│       ├── holestpay_opencart3.php    # OpenCart 3 kontroler
│       └── holestpay_compatibility.php # Prepoznavanje verzije
├── fix_opencart3_compatibility.php    # OpenCart 3 skript za popravku
├── OPENCART3_INSTALLATION.md          # Detaljni vodič za OpenCart 3
└── README.md                          # Ovaj fajl
```

## Podrška

- **Email**: support@pay.holest.com
- **Website**: https://pay.holest.com/support
- **Dokumentacija**: https://docs.pay.holest.com/opencart
- **Problemi sa OpenCart 3**: Pogledajte [OPENCART3_INSTALLATION.md](OPENCART3_INSTALLATION.md)

## Licenca

Ovaj modul je licenciran pod komercijalnom licencom. Molimo kontaktirajte HolestPay za uslove licenciranja.

## Changelog

### Verzija 1.0.0 (2024-12-19)
- Početno izdanje
- Kompletna HolestPay integracija plaćanja
- Podrška za više načina plaćanja
- Načini dostave sa kalkulacijom troškova
- Pretplate i ponavljajuća plaćanja
- Upravljanje Vault tokenima
- Webhook integracija
- Admin upravljanje porudžbinama
- Kompatibilnost sa OpenCart 3 & 4
