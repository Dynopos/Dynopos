# Dyno Ads — Fasa 0 (Create Post)

Auto-create iklan Meta **Click-to-WhatsApp** untuk peniaga kecil. Produk di bawah ekosistem DYNOPRO.

**Fasa 0 = alat single-account untuk pemilik.** Tiada login, tiada OAuth, tiada bayaran.
Token duduk dalam `.env`. Fasa 1 (guna posting sedia ada) hingga Fasa 9 diterangkan dalam
[`CLAUDE.md`](CLAUDE.md) dan [`docs/dyno-ads-spec-v0.2.md`](docs/dyno-ads-spec-v0.2.md).

---

## Flow

```
/buat        Upload 1–4 gambar → masalah → tawaran → telefon → kawasan → bajet
/posting     Pilih 2–4 posting Page sedia ada → kawasan → bajet → campaign PAUSED
/semak/{id}  Caption AI (boleh edit) + pratonton → Approve → campaign dibuat PAUSED
/run/{id}    Butang RUN SEMUA → campaign/adset/ad jadi ACTIVE. Pause per iklan.
/dashboard/{id}  Spend, lead WhatsApp, kos/lead setiap iklan + rekod tindakan
```

Satu gambar = satu campaign. Itu asas split test: kita bandingkan gambar,
bukan bandingkan adset dalam satu campaign.

---

## Setup

```bash
git clone https://github.com/Dynopos/Dynopos.git
cd Dynopos

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite      # Fasa 0 guna sqlite
php artisan migrate
php artisan storage:link            # supaya gambar boleh dipapar

php artisan serve                   # http://localhost:8000
```

Frontend guna Tailwind CDN secara lalai — tak perlu `npm` untuk mula.
Kalau nak build sendiri (lebih laju, saiz kecil):

```bash
npm install && npm run build        # layout auto-guna build kalau manifest wujud
```

### `.env` yang perlu diisi

| Kunci | Contoh | Nota |
|---|---|---|
| `META_ACCESS_TOKEN` | `EAAB…` | System User token, expiry **Never**. Jangan commit. |
| `META_AD_ACCOUNT_ID` | `act_701202512896650` | Prefix `act_` ditambah automatik kalau tertinggal |
| `META_PAGE_ID` | `377330642350146` | Page yang linked dengan nombor WhatsApp |
| `META_WA_PHONE` | `60187922844` | Format antarabangsa, tiada `+` |
| `META_API_VERSION` | `v21.0` | Versi dipin — jangan auto-upgrade |
| `OPENAI_API_KEY` | `sk-…` | Untuk caption. Tukar pembekal dengan `CAPTION_DRIVER` |

---

## Peraturan mutlak (dikuatkuasakan dalam kod)

1. `MetaAdsService` **tiada** method `update*` — nak ubah campaign = buat baru.
2. `AdVariant::isOwnedByApp()` menapis setiap pause/run. Campaign yang bukan app ni buat
   tidak akan disentuh — ia langsung tiada dalam jadual `ad_variants`.
3. Semua campaign/adset/ad dibuat `PAUSED`. Hanya butang RUN yang menghidupkannya.
4. Setiap tindakan direkod dalam `auto_actions` **sebelum** panggilan API dibuat.
5. Token hanya dalam `.env`; `MetaApiException` sengaja tidak memuatkan token dalam mesej.

---

## Setting Meta yang terbukti

Dari 150+ campaign sejarah DynoPOS (kos/lead ~RM10–15). Semuanya duduk dalam
[`config/dynoads.php`](config/dynoads.php) — satu tempat, senang audit.

| Lapisan | Setting |
|---|---|
| Campaign | `OUTCOME_ENGAGEMENT`, budget di campaign (CBO), `LOWEST_COST_WITHOUT_CAP`, `special_ad_categories=[]` |
| Ad set | `CONVERSATIONS`, `destination_type=WHATSAPP`, `billing_event=IMPRESSIONS`, `promoted_object={page_id, whatsapp_phone_number}` |
| Targeting | umur 25–65, `locales=[41]`, `advantage_audience=1`, `location_types=[home,recent]`, brand safety RELAXED |
| Seluruh Malaysia | `countries=[MY]`, exclude region 2546 Sarawak / 2550 Labuan / 2551 Sabah |
| Negeri tertentu | `geo_locations.regions=[{key}]` |
| Ad | link ad, `WHATSAPP_MESSAGE`, `link=https://api.whatsapp.com/send`, gambar via `image_hash` |
| Bajet lalai | RM37/hari (3700 sen) |
| Nama | `DYNOADS-{setID}-{n}-{DDMMMYY}` |

> ⚠️ Satu field perlu disahkan pada akaun sebenar: bila pilih negeri tertentu, kod
> menghantar `targeting_automation.individual_setting.geo_locations = 1` (mengikut setup
> manual). Kalau Meta pulangkan ralat parameter, buang baris tu dalam
> `MetaAdsService::buildTargeting()` — tiada kesan lain.

---

## Deploy

Laravel Forge: lihat [`docs/forge.md`](docs/forge.md) — deploy script, tetapan PHP,
dan cara isi `.env` melalui tab Environment.

## Multi-user (Fasa 7a)

Setiap peniaga ada akaun sendiri, kredential Meta sendiri, dan tidak nampak data
peniaga lain.

| Laluan | Untuk |
|---|---|
| `/daftar`, `/masuk` | akaun peniaga |
| `/sambung` | token, ad account, Page dan nombor WhatsApp peniaga |

Kredential ditentukan di satu tempat sahaja: `App\Services\Meta\MetaCredentials`.
`.env` kekal berfungsi, tetapi **hanya untuk pemilik app** (`users.is_owner`).
Peniaga lain tanpa `FbConnection` aktif tidak boleh membuat iklan — kalau tidak
mereka akan membelanjakan duit pemilik.

### Selepas deploy Fasa 7a — sekali sahaja

Data sedia ada (ad_sets, poster_jobs) mempunyai `user_id` null dan tidak kelihatan
kepada sesiapa sehingga dituntut:

```bash
php artisan migrate
php artisan dynoads:owner borhanlimapos@gmail.com --name="Borhan"
```

Arahan itu mencipta akaun pemilik (kalau belum ada), menetapkan `is_owner`, dan
menyerahkan semua baris yang belum bertuan kepadanya.

Token disimpan berenkripsi (`APP_KEY`). Ia tidak pernah dipapar semula selepas
disimpan, dan tidak pernah masuk ke dalam log atau mesej ralat — peraturan mutlak #8.

## Test

```bash
php artisan test
```

Semua panggilan luar guna `Http::fake()` — test tidak sentuh Meta atau Claude.
Yang diuji: payload campaign/adset/ad, targeting geo, parsing insights
(`onsite_conversion.total_messaging_connection`), pengendalian `error_user_msg`,
retry JSON caption, penapis perkataan larangan, flow Livewire `/buat` →
`/semak` → `/run` → `/dashboard`, dan setiap peraturan mutlak CLAUDE.md
(`tests/Feature/AbsoluteRulesTest.php`).

```bash
vendor/bin/pint --test    # gaya kod
php artisan test          # 34 test
```

CI (`.github/workflows/ci.yml`) jalankan Pint + test pada setiap push.

---

## Semak sebelum tutup Fasa 0

- [ ] 4 campaign muncul PAUSED dalam Ads Manager dengan nama `DYNOADS-…`
- [ ] Tekan RUN → semua jadi ACTIVE dalam Ads Manager
- [ ] Refresh dashboard → spend & lead sepadan dengan Ads Manager
- [ ] Tiada campaign lain dalam akaun tersentuh

## Struktur

```
app/Services/MetaAdsService.php    semua panggilan Graph API (tiada method update*)
app/Services/CaptionService.php    caption AI, fallback bila Claude gagal
app/Services/AdLauncher.php        orkestra DB ⇄ Meta, log auto_actions
app/Services/ImageProcessor.php    crop tengah 1080×1080 (GD)
app/Exceptions/MetaApiException.php  ralat Graph API, mengutamakan error_user_msg
app/Models/                        AdSet, AdVariant, AutoAction, MetricDaily
app/Livewire/AdSets/               Create, Review, Run, Dashboard
resources/views/livewire/ad-sets/  4 skrin, mobile-first, BM santai
config/dynoads.php                 semua setting Meta yang terbukti
docs/dyno-ads-spec-v0.2.md         spec produk penuh
```

## Kawasan

Kunci region Meta tidak pernah ditulis tangan dalam kod. Skrin `/buat` menarik
senarai negeri terus dari `GET /search?type=adgeolocation&country_code=MY`
(cache 24 jam), jadi ia sentiasa sepadan dengan akaun sebenar. Tanpa token,
pilihan kekal "Seluruh Malaysia" — yang sudah pun mengecualikan Sabah, Sarawak
dan Labuan mengikut `config/dynoads.php`.

## Penulis caption

Pembekal boleh ditukar dari `.env` sahaja:

```
CAPTION_DRIVER=openai      # atau claude
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

Prompt, penghuraian JSON, penapis perkataan larangan dan caption ganti semuanya
duduk dalam `CaptionService`. Hanya panggilan HTTP yang berbeza antara pembekal
(`app/Services/Caption/`), jadi menukar pembekal **tidak menukar kualiti copy**.

Bila pembekal gagal atau kunci belum diisi, app memulangkan caption asas yang
boleh diedit — dan `/semak` memaparkan amaran yang menyebut kunci mana yang
hilang. Fallback tidak pernah senyap.

## Gambar dan video

Peniaga muat naik gambar, video, atau campuran kedua-duanya — sehingga empat.
**Satu media = satu campaign.** Itu asas split test: yang menang dikekalkan, yang
mahal dipause.

| | Gambar | Video |
|---|---|---|
| Diproses | dipotong persegi 1080×1080 (GD) | disimpan seadanya |
| Ke Meta | `/adimages` → `image_hash` | `/advideos` → `video_id` |
| Creative | `object_story_spec.link_data` | `object_story_spec.video_data` |
| Thumbnail | — | medan `picture` dari Meta |

Video tidak dipotong persegi. Pelayan ini tiada ffmpeg, dan Meta menerima nisbah
selain 1:1 untuk video — memaksa persegi bermakna memotong kepala orang dalam
video menegak yang peniaga rakam sendiri.

### Video diproses secara tidak segerak

`/advideos` memulangkan `video_id` serta-merta, tetapi creative yang dibuat
sebelum Meta selesai memproses akan ditolak. `MetaAdsService::waitForVideo()`
menunggu sehingga `status.video_status` menjadi `ready` (had masa dalam
`config/dynoads.php`). Tanpa itu, iklan video gagal secara rawak bergantung pada
saiz fail dan beban Meta.

> **Belum disahkan terhadap dokumentasi rasmi Meta.** Bentuk payload video
> datang dari sumber sekunder kerana `developers.facebook.com` tidak boleh
> dicapai dari persekitaran pembangunan. Peraturan mutlak #3 mengehadkan
> kerosakannya — semua dibuat PAUSED, jadi bentuk yang salah gagal dengan ralat
> yang kelihatan semasa Approve, sebelum satu sen dibelanjakan. Uji dengan satu
> video sebenar sebelum mempercayainya.

### Had saiz

Had sebenar ditentukan oleh `upload_max_filesize` dan `post_max_size` dalam
php.ini, bukan oleh config app. Video telefon 30 saat selalunya 20–60 MB, jadi
had lalai 8 MB tidak mencukupi. Skrin Buat memberi amaran awal bila had pelayan
terlalu ketat untuk video — naikkan di Forge → server → PHP.

### Enjin poster telah dibuang

App tidak lagi menjana gambar atau poster. Ia melambatkan setiap permintaan dan
peniaga sudah pun ada gambar sendiri. Jadual `poster_jobs` dan baris
`source_type=poster` **dikekalkan sebagai sejarah** — campaign itu betul-betul
berjalan dan angkanya masih bermakna untuk perbandingan.

## Guna posting sedia ada

`/posting` menyenaraikan posting bergambar dari Page anda — gambar, petikan teks,
tarikh, jumlah reaksi dan komen. Tick 2–4, pilih kawasan dan bajet, dan setiap
posting jadi satu campaign PAUSED seperti biasa.

Creative dibina dengan `object_story_id`, bukan `object_story_spec`. Bezanya
penting kepada peniaga: like, komen dan share terkumpul pada posting asal dia,
jadi bukti sosial tidak berpecah merentas empat iklan asing.

Butang WhatsApp di atas posting sedia ada tidak diterima oleh setiap gabungan
objektif dan format, dan Meta tidak mendokumenkan dengan jelas yang mana. Bila
ia ditolak, app jatuh secara automatik ke laluan kedua: gambar dan teks posting
yang sudah disalin semasa pemilihan digunakan sebagai creative biasa. Iklan tetap
jalan — cuma engagement tinggal pada iklan itu sendiri, bukan pada posting asal.
Setiap kali ini berlaku ia direkod dalam `auto_actions` sebagai
`post_creative_fallback`, berserta ayat yang menjelaskan kesannya, supaya peniaga
tahu tanpa perlu bertanya.

**Peraturan 13:** `PagePostService` hanya membaca. Ia tiada satu pun method yang
menulis, memadam atau mengubah posting, dan ada ujian yang membaca kod sumber
fail itu untuk menguatkuasakannya.

Amaran yang dipapar pada skrin itu kekal penting: posting yang naik secara
organik belum tentu murah kosnya sebagai iklan. Organik diedar kepada peminat
sedia ada; iklan diedar kepada orang asing. Sebab tu ia tetap di-split test.

## Ayat pra-isi WhatsApp

Bila pelanggan tekan butang WhatsApp pada iklan, satu ayat sudah terisi dalam kotak
mesej dia. Kalau kita tidak menetapkannya, **Meta isi ayat defaultnya sendiri dalam
Bahasa Inggeris** ("Hello! Can I get more info on this?"). Pelanggan hantar Inggeris,
dan mana-mana AI agent di hujung sana akan cermin bahasa itu lalu membalas Inggeris —
walaupun iklan dan pelanggan dua-dua orang Malaysia.

Ayat lalai kita: `Hi, saya nak tahu lanjut pasal ni.`
Tukar melalui `DYNOADS_WA_PREFILL` dalam `.env`, atau `config/dynoads.php`.

## Nota Fasa 0

- `AdVariant::isOwnedByApp()` menapis setiap run/pause. Campaign yang bukan app
  ini buat langsung tiada dalam `ad_variants`, jadi ia tak boleh disentuh.
- `activate()` dan `pause()` hanya menghantar medan `status` — tiada budget,
  targeting atau creative dihantar semula. Ini diuji dalam
  `tests/Feature/AbsoluteRulesTest.php`.
- Kalau Claude API gagal, `CaptionService` pulangkan caption asas yang boleh
  diedit. Peniaga tidak tersekat sebab AI down.
