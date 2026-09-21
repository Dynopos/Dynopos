# DYNO ADS — Spec Produk (v0.1)
**Auto-run & auto split test Meta Ads (Click-to-WhatsApp) untuk peniaga kecil**
Disediakan: 2 Sept 2026 · Pemilik: Borhan Sidqy (DYNOPRO) · Status: Draf untuk Claude Code

---

## 1. Ringkasan

Dyno Ads ialah app web (mobile-first) di bawah ekosistem DYNOPRO. Peniaga upload gambar, tulis satu ayat pain point, app tulis caption, create beberapa campaign Meta Click-to-WhatsApp serentak, jalankan split test automatik, matikan yang lemah, buat campaign baru dari pemenang, dan hantar report harian ke WhatsApp peniaga.

Ringkasnya: apa yang agensi ads buat secara manual setiap hari, app ni buat sendiri.

**Positioning:** "Premium Apps. Harga Kaki Lima." — bukan bersaing dengan tool agensi (Windsor, Supermetrics, Madgicx) tapi dengan *peniaga yang tak buat ads langsung sebab tak reti Ads Manager*.

**Nama kerja:** Dyno Ads (logo dino series, warna cadangan: oren).

---

## 2. Masalah yang diselesaikan

| Masalah peniaga | Apa Dyno Ads buat |
|---|---|
| Ads Manager terlalu rumit | 3 skrin je: Upload → Approve → Run |
| Tak tahu caption apa nak tulis | AI draft caption ikut formula "takut → tenang" |
| Buat 1 ad je, tak tahu sama ada gambar/caption tu bagus | Auto split test 4 variasi |
| Lupa nak check, duit bocor pada ad yang tak jalan | Hari ke-3 app kira kos per lead, matikan yang lemah |
| Edit campaign lama, algoritma reset | App **tak pernah edit** campaign — sentiasa buat baru |
| Tak tahu ads jalan ke tak | Report harian masuk WhatsApp |

Sasaran pengguna: warung, kafe, kedai runcit, gift shop, bengkel, klinik kecil — peniaga yang jual lead melalui WhatsApp. Pengguna pertama: DynoPOS sendiri (sudah ada 150+ campaign sejarah untuk validate logik).

---

## 3. Skop MVP (v1) vs kemudian

### v1 — mesti ada
1. Login (email/phone + OTP) + sambung Facebook (OAuth)
2. Pilih Ad Account, Page, nombor WhatsApp yang linked pada Page
3. Buat "Set Iklan": upload 1–4 gambar, isi pain point + tawaran, pilih kawasan (negeri/seluruh Malaysia), bajet harian
4. AI draft caption + headline (boleh edit sebelum approve)
5. Create N campaign (1 gambar = 1 campaign), semua PAUSED, satu butang "Run"
6. Auto split test (logik di §5)
7. Dashboard ringkas: spend, WhatsApp leads, kos/lead setiap campaign
8. Report harian ke WhatsApp pengguna
9. Pembayaran langganan (toyyibPay / Bayarcash / CHIP — FPX)

### v2 — kemudian
- Video ads, carousel
- Boost post sedia ada
- Auto-reply WhatsApp untuk lead masuk (Dyno Auto Contact integration)
- Multi-account (untuk agensi kecil)
- Template caption ikut industri
- Auto-scale bajet pemenang

**Tidak akan buat:** edit/pause campaign yang bukan dibuat oleh app; targeting interest manual (guna Advantage+ audience je); Instagram-only ads.

---

## 4. Flow pengguna (skrin)

```
[0] Landing → Daftar/Login
[1] Sambung Facebook  → pilih Ad Account → pilih Page → sahkan no. WhatsApp
[2] Buat Set Iklan
    ├─ Upload gambar (1–4)          [wajib, 1080×1080 auto-crop]
    ├─ Pain point (1–2 ayat)         [contoh: "hujung hari duit tak sama dengan jualan"]
    ├─ Apa yang anda tawarkan        [contoh: "sistem POS rekod setiap transaksi"]
    ├─ Nombor telefon untuk caption  [boleh 2 nombor]
    ├─ Kawasan: [Seluruh Malaysia] [Pilih negeri…]  (auto-exclude Sabah/Sarawak/Labuan jika mahu)
    └─ Bajet harian per campaign: RM20 / RM37 / RM50 / custom
[3] Semak caption (AI draft) → edit → Approve
[4] Preview 4 iklan → butang "RUN SPLIT TEST"
[5] Dashboard: kad setiap campaign (spend, leads, kos/lead, status), timeline auto-action
[6] Tetapan: langganan, notifikasi WhatsApp, putus sambungan FB, padam data
```

Prinsip UI: satu skrin satu keputusan, bahasa Melayu, tiada istilah Meta (guna "iklan", "kawasan", "bajet", bukan "adset", "CBO").

---

## 5. Logik auto split test (teras produk)

Berdasarkan flow yang divalidasi manual pada 2 Sept 2026 (akaun DynoPOS).

### Fasa 1 — Launch
- Untuk setiap gambar → 1 campaign, 1 ad set, 1 ad
- Campaign: `OUTCOME_ENGAGEMENT`, bajet harian di campaign (CBO), `LOWEST_COST_WITHOUT_CAP`
- Ad set: `optimization_goal=CONVERSATIONS`, `destination_type=WHATSAPP`, `billing_event=IMPRESSIONS`, `promoted_object={page_id, whatsapp_phone_number}`, umur 25–65, `advantage_audience=1`, geo ikut pilihan
- Ad: link ad, `call_to_action_type=WHATSAPP_MESSAGE`, `link=https://api.whatsapp.com/send`, image dari URL app
- Nama: `DYNOADS-{setID}-{gambar#}-{DDMMMYY}` supaya senang tapis dalam Ads Manager
- Semua dibuat PAUSED → aktif hanya bila user tekan Run

### Fasa 2 — Penilaian (hari ke-3, atau bila setiap campaign dah spend ≥ RM60)
- Metrik utama: **kos per WhatsApp lead** = spend ÷ `onsite_conversion.total_messaging_connection`
- Metrik sokongan: CTR, CPC (untuk campaign yang 0 lead)
- Pemenang = 2 terbaik ikut kos/lead (mesti ≥ 3 lead; kalau tak cukup data, lanjut 1 hari, maksimum 2 kali)
- Yang lemah: **pause** (app boleh pause campaign yang ia sendiri buat)

### Fasa 3 — Scale (4 hari)
- Untuk setiap pemenang: **create campaign BARU** (duplicate setting + creative), bukan edit yang lama
- Bajet: sama atau ×1.5 (pilihan user)
- Campaign split test asal: pause bila campaign baru dah aktif
- Selepas 4 hari: report "pusingan tamat", cadang pusingan seterusnya (gambar baru vs caption baru)

### Peraturan tetap
1. App **tidak pernah** sentuh campaign yang bukan ia buat
2. App **tidak pernah** edit budget/targeting campaign sedia ada — sentiasa baru
3. Setiap auto-action direkod dalam timeline + dihantar ke WhatsApp user

---

## 6. Seni bina teknikal

| Lapisan | Pilihan | Nota |
|---|---|---|
| Backend | Laravel 11 (PHP 8.3) | Stack yang Bob dah ada skill audit & SEO |
| DB | MySQL 8 | |
| Queue/cron | Laravel Scheduler + Queue (database/Redis) | Sync metrik setiap 6 jam; penilaian harian 9 pagi |
| Frontend | Blade + Livewire (mobile-first, Tailwind) | Tiada SPA — kekal ringkas |
| Meta | Marketing API v21+ (Graph) | Guna `facebook/php-business-sdk` atau HTTP client terus |
| AI caption | Claude API (`claude-sonnet-4-6`) | Prompt sistem: formula takut→tenang, BM santai, 2 nombor telefon, tiada "demo" |
| Storan gambar | S3-compatible (Cloudflare R2 / DO Spaces) | Meta perlu URL public untuk `image_url`, atau upload terus ke `adimages` (image_hash) — **pilih image_hash**, lebih stabil |
| Report WhatsApp | WhatsApp Cloud API (template `dynoads_daily_report`, kategori utility) | ~RM0.12/mesej; atau guna Reply.la kalau nak cepat |
| Pembayaran | toyyibPay / Bayarcash (FPX) | Langganan bulanan |
| Hosting | VPS 2GB (DigitalOcean/Vultr SG) ~RM40/bulan + Cloudflare | GitHub Pages tak boleh untuk backend |

### Model data (ringkas)
```
users            — id, name, phone, email, plan, consent_at
fb_connections   — user_id, fb_user_id, access_token (encrypted), token_expires_at, ad_account_id, page_id, wa_phone
ad_sets          — user_id, name, pain_point, offer, geo_json, daily_budget, status, phase, round
ad_variants      — ad_set_id, image_path, image_hash, caption, headline, meta_campaign_id, meta_adset_id, meta_ad_id, status
metrics_daily    — ad_variant_id, date, spend, impressions, clicks, leads, cost_per_lead
auto_actions     — ad_set_id, type (launch/pause/scale/report), payload_json, executed_at
subscriptions    — user_id, plan, gateway_ref, paid_until
```

### Token Meta
- OAuth: user login → short-lived token → tukar long-lived (60 hari) → simpan encrypted
- Ingatkan user 7 hari sebelum luput (WhatsApp)
- Pusingan auto-action berhenti jika token luput, bukan crash

---

## 7. Meta App Review — checklist (penentu boleh jual atau tak)

Tanpa lulus review, app hanya boleh guna akaun Bob sendiri (mode Development). Sediakan ini **selari** dengan coding.

**Permissions yang perlu:**
- `ads_management` — create campaign/adset/ad
- `ads_read` — tarik metrik
- `pages_show_list`, `pages_read_engagement` — senarai page
- `business_management` — akses ad account di bawah Business
- `pages_manage_ads` — buat ad atas nama page

**Keperluan:**
1. Meta Business Portfolio dengan **Business Verification** lulus (SSM, alamat, no. telefon bisnes)
2. App di developers.facebook.com, jenis Business, produk: Facebook Login for Business + Marketing API
3. **Privacy Policy URL** (BM+EN) di dynopro.my
4. **Data Deletion Callback URL** (endpoint yang padam data user bila diminta Meta)
5. **Screencast** 2–5 minit menunjukkan flow penuh: login FB → pilih account → create campaign — guna akaun test
6. Penerangan use case setiap permission dalam BM/EN (kenapa perlu, apa user dapat)
7. Terma Perkhidmatan URL
8. Tech Provider: tidak perlu jika app dijalankan atas nama pengguna sendiri (mereka login FB masing-masing)

**Anggaran masa:** 2–6 minggu, biasanya 1–2 kali reject sebelum lulus. Mula submit sebaik MVP boleh demo flow penuh.

---

## 8. Model harga (cadangan, "harga kaki lima")

| Pelan | Harga | Had |
|---|---|---|
| Percuma | RM0 | 1 set iklan, 2 variasi, tiada auto-scale, report mingguan |
| Peniaga | RM39/bulan | 3 set iklan aktif, 4 variasi, auto split test + scale, report harian WhatsApp |
| Pro | RM99/bulan | Tanpa had set, 2 ad account, priority support |

Kos sebenar per user Pro: WhatsApp ~RM4/bulan + Claude API <RM1 + hosting dikongsi. Margin sihat pada RM39.

Bajet ads dibayar terus oleh user kepada Meta — app tidak pegang duit ads (elak isu lesen/kewangan).

---

## 9. PDPA (Akta 709 + Pindaan 2024)

Data yang dipegang: nama, telefon, email, token Facebook, ID ad account/page, gambar iklan.

- Consent checkbox berasingan: (a) akaun & perkhidmatan, (b) notifikasi WhatsApp, (c) pemasaran DYNOPRO
- Privacy Notice BM di titik daftar dan titik sambung FB
- Token FB disimpan encrypted (Laravel `encrypt()`), tidak pernah dilog
- Skrin "Data Saya": lihat, betulkan, muat turun, padam akaun
- Retention: padam token & data 90 hari selepas akaun tidak aktif; metrik dikekalkan tanpa pengenalan
- Data Breach Notification: proses dalaman untuk lapor kepada Pesuruhjaya dalam 72 jam
- Log akses kepada `fb_connections`

---

## 10. Risiko & mitigasi

| Risiko | Mitigasi |
|---|---|
| Meta App Review ditolak | Submit awal, screencast jelas, minta hanya permission yang perlu |
| Meta tukar API/objective | Abstrak lapisan Meta dalam satu service class; versi API dipin |
| Ad account user kena restrict (bukan salah app) | Papar status akaun dari Meta dalam dashboard; jangan auto-retry membabi buta |
| User expect "auto = pasti untung" | Onboarding jelas: app optimize, tak jamin lead; papar benchmark (kos/lead purata industri) |
| Token luput, auto-action berhenti senyap | Peringatan WhatsApp 7 hari awal + banner dashboard |
| Caption AI keluar benda pelik | User wajib approve sebelum Run; senarai perkataan larangan |

---

## 11. Roadmap

| Minggu | Sasaran |
|---|---|
| 1 | Setup Laravel, auth, FB OAuth, pilih account/page. Mula Business Verification. |
| 2 | Set Iklan: upload, AI caption, create campaign (paused) atas akaun DynoPOS. Draft Privacy Policy + Data Deletion endpoint. |
| 3 | Sync metrik, dashboard, logik penilaian + scale. Screencast → submit App Review. |
| 4 | Report WhatsApp, pembayaran FPX, landing page dynoads (SEO). Beta dengan 5 peniaga DynoPOS. |
| 5–8 | Iterasi ikut App Review + feedback beta. Lancar. |

---

## 12. Urutan build — "Create Post" dulu

Fasa 0 = alat untuk Bob sendiri (single account, token dalam .env). Tiada login, tiada FB OAuth, tiada bayaran. Bila Fasa 0 dah stabil dan dipakai harian, baru tambah multi-user.

| Fasa | Apa | Bila |
|---|---|---|
| **0 — Create Post** | Upload gambar → AI caption → create N campaign paused → Run → dashboard ringkas | Minggu 1–2 |
| 1 — Auto split test | Sync metrik, penilaian hari ke-3, pause lemah, scale pemenang (campaign baru) | Minggu 3 |
| 2 — Multi-user | Daftar, FB OAuth, pilih account/page, PDPA, App Review | Minggu 4–6 |
| 3 — Jual | Pembayaran FPX, landing page, report WhatsApp | Minggu 7–8 |

### Prompt Fasa 0 untuk Claude Code (salin terus)

```
Bina app Laravel 11 bernama "Dyno Ads" ikut spec dyno-ads-spec.md (dilampirkan).
FASA 0 SAHAJA — alat single-account untuk pemilik. Tiada login, tiada OAuth, tiada bayaran.

Konfigurasi dalam .env:
  META_ACCESS_TOKEN, META_AD_ACCOUNT_ID=act_701202512896650, META_PAGE_ID=377330642350146,
  META_WA_PHONE=60187922844, META_API_VERSION=v21.0, ANTHROPIC_API_KEY

Bina:
1. Laravel 11 + Livewire 3 + Tailwind, mobile-first, bahasa Melayu, satu skrin satu keputusan.
2. Migration & model: ad_sets, ad_variants, metrics_daily, auto_actions (ikut §6, tanpa users/fb_connections buat masa ni).
3. App\Services\MetaAdsService (semua panggilan Meta lalu sini, versi dipin):
   - uploadImage(path) → POST /act_{id}/adimages → image_hash
   - createCampaign(name, dailyBudgetSen) → OUTCOME_ENGAGEMENT, CBO, LOWEST_COST_WITHOUT_CAP, PAUSED
   - createAdSet(campaignId, name, geo) → CONVERSATIONS, WHATSAPP, IMPRESSIONS, promoted_object {page_id, whatsapp_phone_number}, umur 25–65, advantage_audience=1, PAUSED
   - createAd(adsetId, name, imageHash, message, headline) → link ad, WHATSAPP_MESSAGE, link=https://api.whatsapp.com/send, PAUSED
   - enable(campaignId, adsetId, adId), pause(...)
   - getInsights(campaignIds, since, until) → spend, impressions, clicks, onsite_conversion.total_messaging_connection
4. App\Services\CaptionService guna Claude API (claude-sonnet-4-6):
   - input: pain point, tawaran, 1–2 nombor telefon
   - output JSON {caption, headline}, BM santai, formula "takut → tenang", CTA "Tekan WhatsApp untuk info lanjut", sertakan nombor telefon, JANGAN sebut "demo".
5. Skrin "Buat Set Iklan": upload 1–4 gambar (auto crop 1080×1080), pain point, tawaran, nombor telefon, kawasan (Seluruh Malaysia / pilih negeri, exclude Sabah/Sarawak/Labuan optional), bajet harian (RM20/37/50/custom).
6. Skrin "Semak": caption+headline boleh edit → Approve → app create N campaign PAUSED (1 gambar = 1 campaign), nama DYNOADS-{setID}-{n}-{DDMMMYY}.
7. Skrin "Run": senarai campaign yang dibuat + butang RUN (enable semua) + butang PAUSE per campaign. Hanya campaign yang app buat sendiri boleh diusik.
8. Dashboard: kad per campaign — spend, leads, kos/lead, status (dari getInsights; refresh manual dulu).
9. Log setiap tindakan dalam auto_actions.

Peraturan: jangan pernah edit budget/targeting campaign sedia ada; jangan sentuh campaign yang bukan app buat. Tulis test untuk MetaAdsService dan CaptionService guna Http::fake(). README ringkas cara run (php artisan serve).
```

Bila Fasa 0 siap dan Bob dah guna 1–2 minggu, prompt Fasa 1 (auto split test) menyusul.

---

*Rujukan dalaman: split test manual 2 Sept 2026 — akaun 701202512896650, page 377330642350146, campaign WINDSORAI TEST & WINDSORAI POST-E1..E4.*
