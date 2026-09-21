# Dyno Ads — Spec v0.2

> Dokumen ini disusun semula daripada `CLAUDE.md` dan README semasa Fasa 0 dibina.
> Ia rujukan kerja untuk fasa-fasa berikutnya. Kalau ada percanggahan,
> **`CLAUDE.md` yang menang** — di situ peraturan mutlak duduk.

---

## §1 Masalah

Peniaga kecil Malaysia nak lead WhatsApp. Ads Manager terlalu rumit: objektif,
adset, CBO, bid strategy, brand safety. Mereka akhirnya tekan "Boost Post" dan
bakar duit tanpa perbandingan.

Dyno Ads buang semua istilah tu. Peniaga jawab lima soalan, tengok pratonton,
tekan satu butang. App yang tahu setting mana yang terbukti.

## §2 Prinsip

1. **Satu skrin satu keputusan.** Mobile-first, Bahasa Melayu santai.
2. **Tiada duit tanpa satu klik manusia.** Semua objek dibuat PAUSED.
3. **App tidak pernah menyentuh apa yang bukan ia buat** — campaign lain dalam
   akaun, dan posting Page sedia ada.
4. **Nak ubah = buat baru.** Tiada `update*` pada campaign sedia ada.
5. **LLM isi medan, app validate, manusia sahkan, app panggil Meta.**
   LLM tidak pernah memanggil Meta API.

## §3 Chat AI team (Fasa 4)

Peniaga bercerita; AI Amir tanya balik satu soalan satu mesej sampai brief penuh.

Jadual: `conversations`, `messages`, `campaign_briefs`.

`campaign_briefs` mengandungi objektif, produk, destinasi (WhatsApp), sumber
creative (poster / posting sedia ada), bilangan creative, bajet harian,
bilangan hari, **had maksimum**, lokasi, umur, dan cap masa pengesahan.

Tool yang dibenarkan untuk LLM: `set_field`, `ask_user`, `request_upload`,
`propose_creatives`, `list_existing_posts`, `request_confirmation`.
Tiada tool menulis ke Meta. Setiap `set_field` disahkan terhadap skema;
gagal validate → AI tanya semula, tidak meneka.

Had maksimum dikuatkuasakan di Meta juga (tarikh tamat / lifetime budget),
bukan sekadar dipapar.

## §4 Creative

### §4A Creative datang dari peniaga (Fasa 2–3)

Enjin poster dan latar AI dibatalkan 21 Sept 2026. App tidak menjana gambar atau video.

- **Gambar dan video** — peniaga muat naik 2–4 media berbeza. AI jana satu caption untuk
  setiap satu. Satu media = satu campaign. Yang paling murah kos/lead dikekalkan, yang
  lemah dipause.
- Ini ujian peringkat creative: media dan caption berubah serentak, jadi satu ujian
  menjawab "yang mana menang" tetapi bukan "kenapa". Diterima dengan sedar — peniaga
  dengan bajet RM37/hari tidak mampu kitaran ujian terkawal.
- **Video** — muat naik ke `/advideos` tak segerak, jadi campaign hanya dicipta selepas
  Meta selesai memproses. Thumbnail wajib.
- `ad_variants.media_type` (`image` | `video`) berasingan daripada `source_type` — keduanya
  ortogonal; posting sedia ada pun boleh jadi video.
- `ad_variants.hook` merekod sudut yang dipakai, supaya Fasa 5 boleh melaporkan sudut mana
  menang merentas kempen, bukan hanya nombor creative dalam satu kempen.

Kos yang diterima secara sedar: halangan "tiada gambar cantik" kembali. Sebahagian peniaga
tidak akan beriklan kerana itu.

### §4B Posting sedia ada (Fasa 1)

Peniaga pilih 2–4 posting yang dia dah ada di Page; app split test antara posting.
Tiada poster, tiada caption baru, tiada permission baru.

- `ad_variants.source_type` = `poster` | `existing_post`, `source_post_id` nullable.
- `PagePostService::listPosts()` — `GET /{page_id}/posts` dengan fields
  `id, message, full_picture, permalink_url, created_time, shares,
  reactions.summary(true), comments.summary(true)`. Posting bergambar sahaja.
  Cache 10 minit, paging cursor. **Baca sahaja** — tiada POST/DELETE (peraturan 13).
- `MetaAdsService::createAdFromPost()` — creative guna
  `object_story_id = "{page_id}_{post_id}"` dengan CTA `WHATSAPP_MESSAGE`.
  Kalau Meta tolak, jatuh ke laluan kedua: salin gambar + mesej jadi creative
  baru, dan rekod dalam `auto_actions` supaya user tahu like/komen **tidak**
  akan terkumpul pada posting asal.
- Amaran satu baris di skrin pilih: "Posting yang naik secara organik belum tentu
  murah kosnya sebagai iklan — sebab tu kita tetap split test."

## §5 Setting Meta yang terbukti

Dari 150+ campaign DynoPOS, kos/lead ~RM10–15. Semua dalam `config/dynoads.php`.

| Lapisan | Setting |
|---|---|
| Campaign | `OUTCOME_ENGAGEMENT`, CBO, `LOWEST_COST_WITHOUT_CAP`, `special_ad_categories=[]` |
| Ad set | `CONVERSATIONS`, `destination_type=WHATSAPP`, `billing_event=IMPRESSIONS`, `promoted_object={page_id, whatsapp_phone_number}` |
| Targeting | umur 25–65, `locales=[41]`, `advantage_audience=1`, `location_types=[home,recent]`, brand safety RELAXED |
| Geo | `countries=[MY]` tolak 2546 Sarawak, 2550 Labuan, 2551 Sabah |
| Ad | link ad, `WHATSAPP_MESSAGE`, `link=https://api.whatsapp.com/send`, `image_hash` |
| Bajet | RM37/hari lalai · nama `DYNOADS-{setID}-{n}-{DDMMMYY}` |

**Satu gambar = satu campaign.** Itu asas split test: kita bandingkan creative,
bukan bandingkan adset dalam satu campaign.

## §6 Auto split test & guard (Fasa 5)

- `dynoads:sync-metrics` setiap 30 minit → `metrics_daily`. **Paparan sahaja.**
- Guard pembaziran: pause hanya jika spend ≥ RM40 **DAN** 0 lead **DAN** CTR < 0.5%.
  Iklan yang ada lead tidak dipause walaupun mahal.
- `SplitTestService::evaluate` — hari ke-3 atau setiap variant spend ≥ RM60.
  Pemenang = 2 terbaik ikut kos/lead dengan ≥3 lead. Data tak cukup → tunggu
  1 hari, maksimum 2 kali, lepas tu ikut CTR.
- `SplitTestService::scale` — campaign **BARU** untuk pemenang
  (`parent_variant_id`), bajet sama atau ×1.5; campaign asal dipause selepas yang
  baru ACTIVE. Tamat +4 hari → status done.

## §7 Report (Fasa 6)

Template utility `dynoads_daily_report`, hantar 8:00 pagi Asia/Kuala_Lumpur:
belanja semalam, lead, kos/lead, iklan terbaik, tindakan app semalam, satu
cadangan. Tindakan besar (pause / campaign baru) dimaklumkan segera.
Kegagalan hantar direkod tetapi tidak menghentikan automasi.

## §8 Multi-user (Fasa 7)

Auth Breeze + OTP telefon. Facebook Login for Business via Socialite.
Scope pusingan 1: `ads_management`, `ads_read`, `business_management`,
`pages_show_list`, `pages_read_engagement`, `pages_manage_ads`.
Token long-lived, encrypted dalam `fb_connections`. Semua model ada `user_id`
+ policy isolation. Peringatan token luput 7 hari awal.

## §9 PDPA & pelan

**PDPA (Act 709).** Consent berasingan untuk data iklan dan untuk pemasaran.
Privacy Notice dalam BM. Skrin "Data Saya" (papar, eksport, padam).
Endpoint `POST /meta/data-deletion` yang verify `signed_request`.
Retention 90 hari untuk data mentah insights.

**Pelan.** Percuma / Peniaga RM49 / Pro RM129. Kuota: campaign aktif, creative,
latar AI sebulan. Guna posting sedia ada **tidak** makan kuota latar AI — itu
daya tarikan pelan Percuma. Bayaran toyyibPay FPX.

## §10 Fasa

0 Create Post ✅ · 1 Posting sedia ada · 2 Enjin poster · 3 Latar AI ·
4 Chat AI team · 5 Auto split test · 6 Report WhatsApp · 7 Multi-user ·
8 Jual · 9 Posting organik

Satu PR satu fasa. Definition of Done setiap fasa ada dalam `CLAUDE.md`.
