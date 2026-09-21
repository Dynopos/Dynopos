# CLAUDE.md — Dyno Ads
Fail ini duduk di root repo **`Dynopos/Dynopos`** — bukan `Dynopos/Dynoads`.
Repo `Dynoads` wujud tetapi kosong; jangan push ke sana. Forge (`dynoads.my`)
menarik dari `Dynopos/Dynopos:main`. Claude Code baca fail ini setiap sesi.
Spec penuh: `docs/dyno-ads-spec-v0.2.md` (v0.1 disimpan di `docs/dyno-ads-spec.md` sebagai sejarah).

---

## Apa projek ni
App Laravel yang ambil gambar atau video peniaga, tulis copy, lancar iklan Meta
**Click-to-WhatsApp**, pantau prestasi dan hantar report — untuk peniaga kecil Malaysia. Produk di bawah DYNOPRO.
Pengguna pertama: DynoPOS (pemilik repo).

## Status
- **Fasa 0 — SIAP.** Borang Create → Review → Run → Dashboard, campaign PAUSED, insights.
- **Fasa 1 — SIAP** (PR #2): guna posting Page sedia ada sebagai creative.
- Fasa 2 seterusnya: **satu gambar, banyak hook**. Jangan mula fasa lain sebelum ni stabil.
- Enjin poster dan latar AI **dibatalkan** — lihat nota arah di bawah.
- Satu PR satu fasa. Jangan gabung.

## Stack (jangan tukar tanpa tanya)
- Laravel 11, PHP 8.5, MySQL 8 (Fasa 0 sqlite), Livewire 3, Tailwind, Blade
- Meta Marketing API v21.0 — semua panggilan lalu `App\Services\MetaAdsService`
- Claude API — semua panggilan lalu service khusus (`CaptionService`, `ChatService`), tak pernah dari Livewire
- Queue: database driver dulu, Redis kemudian
- Test: Pest, `Http::fake()` untuk semua panggilan luar

## Peraturan mutlak
1. **Jangan pernah edit** budget/targeting/creative campaign sedia ada di Meta. Nak ubah = buat campaign baru.
2. **Jangan sentuh** campaign yang bukan app ni buat. Kenal pasti dari `ad_variants.meta_campaign_id`.
3. Semua campaign/adset/ad dibuat **PAUSED**. Aktif hanya bila user tekan Run.
4. Setiap tindakan yang ubah Meta → rekod dalam `auto_actions` dulu, baru panggil API.
5. **LLM tidak pernah memanggil Meta API.** LLM isi medan; app validate; manusia sahkan; app panggil Meta.
6. **Tiada duit dibelanjakan tanpa satu klik pengesahan manusia yang jelas.**
7. **App tidak menjana gambar atau video.** Semua visual datang dari peniaga — dimuat naik
   sendiri, atau posting Page yang dia sudah ada. AI menulis teks sahaja.
8. Token/API key hanya dalam `.env`. Jangan log, jangan commit, jangan tulis dalam chat.
9. Bahasa UI: Melayu santai. Istilah user: "iklan", "kawasan", "bajet", "lead". Bukan "adset", "CBO", "conversion".
10. Mobile-first. Satu skrin satu keputusan.
11. CTA iklan: "Tekan WhatsApp untuk info lanjut". Jangan guna perkataan "demo".
12. Jangan tiru nama watak, susunan skrin atau ayat pemasaran pesaing. Identiti Dyno sendiri.
13. **Jangan sekali-kali edit, padam atau ubah posting Page sedia ada.** App hanya merujuk `post_id` untuk dijadikan creative.

## Kru AI (persona UI sahaja — satu model, system prompt berbeza)
AI Amir (Ketua Projek) · AI Nurin (Penulis) · AI Zaki (Pereka) · AI Hana (Penganalisis)
Avatar = ilustrasi siri dino. Jangan guna gambar muka manusia.

## Konfigurasi (Fasa 0)
```
META_ACCESS_TOKEN=          # System User token, expiry Never
META_AD_ACCOUNT_ID=act_701202512896650
META_PAGE_ID=377330642350146
META_WA_PHONE=60187922844
META_API_VERSION=v21.0
ANTHROPIC_API_KEY=
```

## Setting Meta yang terbukti (jangan ubah tanpa data)
Semua duduk dalam `config/dynoads.php`. Dari 150+ campaign DynoPOS, kos/lead ~RM10–15.
- Campaign: `OUTCOME_ENGAGEMENT`, budget di campaign (CBO), `LOWEST_COST_WITHOUT_CAP`, `special_ad_categories=[]`
- Ad set: `CONVERSATIONS`, `destination_type=WHATSAPP`, `billing_event=IMPRESSIONS`, `promoted_object={page_id, whatsapp_phone_number}`
- Targeting: umur 25–65, `locales=[41]`, `advantage_audience=1`, `location_types=[home,recent]`, brand safety RELAXED
- Geo Malaysia: `countries=[MY]` + exclude 2546 Sarawak, 2550 Labuan, 2551 Sabah
- Ad: link ad, `WHATSAPP_MESSAGE`, `link=https://api.whatsapp.com/send`, gambar via `image_hash`
- Bajet lalai RM37/hari · Nama `DYNOADS-{setID}-{n}-{DDMMMYY}`

## Struktur folder
```
app/Services/MetaAdsService.php            Graph API (tiada method update* — sengaja)
app/Services/CaptionService.php            caption AI
app/Services/AdLauncher.php                orkestra DB ⇄ Meta
app/Services/ImageProcessor.php            crop 1080×1080
app/Services/PagePostService.php           (Fasa 1 — senarai posting Page)
app/Services/VideoUploader.php             (Fasa 3 — muat naik video ke Meta)
app/Services/Chat/ChatService.php          (Fasa 4)
app/Services/SplitTestService.php          (Fasa 5)
app/Livewire/AdSets/{Create,Review,Run,Dashboard}.php
config/dynoads.php                         semua setting Meta
docs/dyno-ads-spec-v0.2.md
```

## Definition of Done (setiap fasa)
- `php artisan test` hijau · `vendor/bin/pint --test` bersih
- Flow fasa tu jalan hujung ke hujung atas `php artisan serve`
- README dikemaskini · tiada token dalam kod
- Branch `claude/fasa-{n}-{ringkasan}` → PR ke `main`

---
# FASA 1 — Guna posting sedia ada
**Matlamat:** peniaga pilih 2–4 posting yang dia dah ada di Page, app split test antara posting tu.
Tiada poster, tiada caption baru, tiada permission baru. Laluan terpantas kepada nilai.

```
Baca CLAUDE.md dan docs/dyno-ads-spec-v0.2.md §4B. Laksanakan FASA 1 sahaja.

1. Migration: ad_variants tambah source_type (poster|existing_post, default poster)
   dan source_post_id nullable.
2. App\Services\PagePostService:
   - listPosts(int $limit = 25): GET /{page_id}/posts dengan fields id, message,
     full_picture, permalink_url, created_time, shares,
     reactions.summary(true), comments.summary(true). Tapis posting bergambar sahaja.
   - Cache 10 minit. Paging cursor.
   - PERATURAN 13: tiada method yang menulis, memadam atau mengubah posting. Baca sahaja.
3. MetaAdsService::createAdFromPost(adsetId, name, pageId, postId):
   - creative guna object_story_id = "{page_id}_{post_id}"
   - cuba hantar call_to_action WHATSAPP_MESSAGE bersamanya
   - kalau Meta tolak (tangkap error code/subcode), jatuh secara automatik ke laluan kedua:
     salin gambar + mesej posting jadi creative baru (guna createAd sedia ada), dan
     rekod dalam auto_actions bahawa fallback digunakan supaya user tahu like/komen
     TIDAK akan terkumpul pada posting asal.
4. Livewire skrin "Guna posting sedia ada": grid posting (gambar, petikan mesej, tarikh,
   bilangan reaksi/komen), tick 2–4, pilih kawasan + bajet, lalu guna AdLauncher sedia ada.
   AdLauncher::launch() kena kenal source_type dan panggil createAdFromPost bila perlu.
5. Skrin Create sedia ada dapat pilihan di atas: [Poster/gambar baru] [Posting sedia ada].
6. Amaran satu baris di skrin pilih: "Posting yang naik secara organik belum tentu murah
   kosnya sebagai iklan — sebab tu kita tetap split test."
7. Test Pest (Http::fake): listPosts memparse reaksi/komen betul; createAdFromPost hantar
   object_story_id dengan format {page}_{post}; fallback berlaku bila Meta pulangkan ralat
   CTA dan direkod dalam auto_actions; PagePostService tiada panggilan POST/DELETE langsung.
```

**Semak sebelum tutup fasa**
- [ ] Posting DynoPOS sebenar tersenarai dengan gambar dan engagement betul
- [ ] Pilih 3 posting → 3 campaign PAUSED muncul di Ads Manager
- [ ] Tekan RUN → like/komen baru masuk ke posting asal (kalau laluan object_story_id berjaya)
- [ ] Tiada posting sedia ada yang berubah, terpadam atau hilang

---

# NOTA ARAH — kenapa enjin poster dibatalkan

Fasa 2 lama ialah enjin poster (template Blade + Playwright), Fasa 3 lama ialah latar AI.
Kedua-duanya dibatalkan pada 21 Sept 2026. Sebabnya:

**Kos operasi.** Chromium gagal dipasang empat kali atas sebab berbeza, timeout render
berlanggar dengan had PHP-FPM, dan had muat naik php.ini memecahkan skrin. Semuanya
direkod dalam `docs/forge.md`. Itu permukaan kegagalan yang besar untuk satu ciri.

**Peniaga sudah ada creative.** Spec sendiri menyenaraikan gambar peniaga sebagai sumber
terbaik. Ramai juga sudah ada video dalam telefon. Meminta mereka bawa creative sendiri
bukan meninggalkan tesis produk — ia menjadikan laluan terbaik itu satu-satunya laluan.

**Apa yang diterima sebagai kos.** Halangan "tiada gambar cantik" kembali; sebahagian
peniaga tidak akan beriklan kerana itu. Keputusan ini dibuat dengan sedar.

Kod poster sedia ada (`app/Services/Poster/*`, `resources/poster/`, `poster_jobs`) kekal
dalam repo buat masa ini supaya set iklan lama tidak pecah. Ia tidak dibangunkan lagi.

---

# FASA 2 — Rekod apa yang menang, bukan hanya siapa

**Matlamat:** setiap variant merekod media jenis apa dan sudut copy mana yang dipakai,
supaya selepas beberapa bulan sistem boleh beritahu peniaga sesuatu yang berguna.

**Kenapa fasa ini kecil:** aliran yang pemilik mahu — peniaga bagi 2–4 media berbeza,
app jana satu caption untuk setiap satu, satu campaign setiap satu, yang menang dikekalkan
— **sudah terbina sejak Fasa 0**. Yang tiada hanyalah ingatan: Dashboard boleh kata
"Creative #2 menang", tetapi tiada apa merekod bahawa #2 itu video dengan sudut bantahan.

Tanpa dua lajur ini, setiap set iklan bermula dari kosong. Dengan ia, Fasa 5 boleh
mengumpul merentas kempen: *"video menang 6 daripada 8 kali untuk bisnes anda"*.

**Had yang perlu difahami:** setiap ujian mengubah media DAN caption serentak, jadi satu
ujian tidak boleh membuktikan yang mana menyebabkan kemenangan. Itu pertukaran yang
disengajakan — peniaga dengan bajet RM37/hari tidak mampu kitaran ujian terkawal. Tetapi
merentas 50 set iklan, corak yang berulang tetap bermakna walaupun satu-satu ujian tidak.

```
Baca CLAUDE.md. Laksanakan FASA 2 sahaja.

1. Migration: ad_variants tambah `hook` (string, nullable) dan `media_type`
   (image|video, default image). JANGAN muatkan media_type ke dalam source_type —
   keduanya ortogonal; posting sedia ada pun boleh jadi video.
2. CaptionService::generate() pulangkan {caption, hook} untuk setiap sudut, bukan
   string sahaja. Prompt sudah menyenaraikan lima sudut — namakannya dalam respons
   JSON supaya app boleh simpan yang mana dipakai.
3. Review simpan hook bersama caption bila variant dikemaskini.
4. Dashboard papar hook dan media_type setiap variant.
5. Test Pest: hook disimpan dari respons AI; variant lama tanpa hook tidak pecah.
```

**Semak sebelum tutup fasa**
- [ ] Dashboard beritahu "video · sudut bantahan" bukan hanya "Creative #2"
- [ ] Set iklan lama yang tiada hook masih dipapar tanpa ralat

---

# FASA 3 — Video
**Matlamat:** peniaga muat naik video sendiri, atau gambar dan video sekali.

**Ini bukan "satu lagi jenis fail".** Video mengubah tiga perkara asas, dan setiap satu
boleh gagal sendiri:

| | Gambar | Video |
|---|---|---|
| Muat naik | `POST /act_x/adimages` → `image_hash` | `POST /act_x/advideos` → `video_id` |
| Pemprosesan | Serta-merta | **Tak segerak** — Meta proses dahulu |
| Creative | `object_story_spec.link_data` | `object_story_spec.video_data` |
| Thumbnail | Tak perlu | **Wajib** |

Butiran API di atas belum disahkan terhadap dokumentasi v21.0. **Sahkan dahulu sebelum
menulis kod** — bentuk `video_data` dan cara polling status adalah andaian.

```
Baca CLAUDE.md dan docs/dyno-ads-spec-v0.2.md §4. Laksanakan FASA 3 sahaja.

1. Sahkan bentuk video_data dan status polling terhadap dokumentasi Marketing API v21.0.
   Betulkan pelan ini kalau ia berbeza daripada jadual di atas.
2. Migration: ad_variants tambah `meta_video_id` dan `thumbnail_path`.
   (`media_type` sudah ditambah dalam Fasa 2.)
3. App\Services\VideoUploader: muat naik ke /advideos, pulangkan video_id.
4. Queue job meninjau status video sampai sedia, kemudian cipta creative dan ad.
   Approve tidak lagi segerak. Skrin Run kena tunjuk "sedang diproses" dengan jujur.
5. Thumbnail: ambil daripada Meta selepas proses, atau benarkan peniaga pilih frame.
6. Had muat naik pelayan: upload_max_filesize, post_max_size, client_max_body_size
   nginx, dan timeout PHP-FPM serta nginx. Uploads::maxKilobytes() ada siling keras
   8192 — naikkan. Video telefon lazimnya 20–100MB.
7. Test Pest (Http::fake): video_data dihantar dengan video_id dan thumbnail;
   creative tidak dicipta sebelum status video sedia; kegagalan proses direkod
   dalam auto_actions dan tidak menggantung queue.
```

**Semak sebelum tutup fasa**
- [ ] Video 60MB dari telefon berjaya dimuat naik tanpa ralat
- [ ] Campaign hanya dicipta selepas Meta selesai memproses video
- [ ] Peniaga nampak status sebenar semasa menunggu, bukan skrin beku

---

# KEPUTUSAN — apa yang setiap split test ubah

**Diputuskan 21 Sept 2026.** Peniaga bagi 2–4 media berbeza. App jana satu caption untuk
setiap satu. Satu media = satu campaign. Yang paling murah kos/lead dikekalkan.

Ini ujian peringkat **creative**, bukan ujian pemboleh ubah tunggal. Media dan caption
berubah serentak, jadi satu ujian menjawab "yang mana menang" tetapi bukan "kenapa".
Diterima dengan sedar: peniaga dengan bajet RM37/hari tidak mampu kitaran ujian terkawal,
dan yang dia mahu ialah lead murah, bukan kertas kajian.

Lajur `hook` dan `media_type` (Fasa 2) ialah cara mendapat semula sebahagian "kenapa" itu —
bukan daripada satu ujian, tetapi daripada corak merentas puluhan set iklan.

Nota: saranan Meta "guna gambar + video" bermaksud letak kedua-duanya dalam **ad set yang
sama** supaya algoritma penghantaran boleh memilih per-orang. Seni bina kita sengaja letak
satu creative satu campaign supaya KITA boleh membandingkan. Dua strategi berbeza — jangan
jangka faedah pertama daripada susunan kedua.

---

# FASA 4 — Chat AI team
**Matlamat:** peniaga bercerita, app faham, campaign siap untuk disahkan.

```
Baca spec §3. Laksanakan FASA 4.

1. Migration: conversations, messages, campaign_briefs (medan penuh ikut spec §3).
2. App\Services\Chat\ChatService — Claude API dengan tool use.
   Tools DIBENARKAN: set_field, ask_user, request_upload, propose_creatives,
   list_existing_posts, request_confirmation.
   TIADA tool yang memanggil Meta untuk menulis. Ini peraturan mutlak #5.
3. Setiap set_field disahkan terhadap skema. Gagal validate → AI tanya semula, jangan teka.
4. Livewire chat: satu soalan satu mesej, butang pilihan bila boleh (RM20/30/50, 7/14/30 hari),
   avatar persona, dan butang "Guna borang biasa" sentiasa ada.
   AI Amir kena tanya awal: "Nak guna posting yang dah ada, atau kita buat poster baru?"
5. Skrin sahkan sebelum lancar: objektif, produk, destinasi, bilangan creative, bajet×hari,
   HAD MAKSIMUM, lokasi, audiens. Had maksimum dikuatkuasakan di Meta juga
   (tarikh tamat / lifetime budget), bukan sekadar dipapar.
6. Dari brief disahkan → creative (poster atau posting sedia ada) → AdLauncher::launch()
   PAUSED → skrin Run sedia ada.
7. Ayat tetap di bawah kotak chat: "AI boleh buat silap — anda sahkan sendiri sebelum
   apa-apa duit dibelanjakan."
8. Test: perbualan penuh mengisi semua medan; input tak sah tidak diterima; brief yang
   belum disahkan tidak boleh dilancarkan.
```

---

# FASA 5 — Auto split test & guard
```
Baca spec §6. Laksanakan FASA 5.

1. Command dynoads:sync-metrics — setiap 30 minit, simpan metrics_daily. PAPARAN SAHAJA.
2. Guard pembaziran (setiap sync): pause hanya jika spend ≥ RM40 DAN 0 lead DAN CTR < 0.5%.
3. SplitTestService::evaluate — hari ke-3 atau setiap variant spend ≥ RM60.
   Pemenang = 2 terbaik ikut kos/lead dengan ≥3 lead. Data tak cukup → tunggu 1 hari,
   maksimum 2 kali, lepas tu ikut CTR.
4. SplitTestService::scale — campaign BARU untuk pemenang (parent_variant_id), bajet sama
   atau ×1.5, pause campaign asal selepas yang baru ACTIVE. Tamat +4 hari → status done.
   Untuk source_type=existing_post, campaign baru guna object_story_id yang sama.
5. Command dynoads:evaluate — harian 9:00 Asia/Kuala_Lumpur.
6. Timeline auto_actions dipapar dalam dashboard. Laporan kena boleh bandingkan
   prestasi poster vs posting sedia ada.
7. Test: seed 4 variant metrik berbeza → sahkan pemenang, pause, campaign baru.
   Sahkan guard TIDAK pause iklan yang ada lead walaupun mahal.
```

---

# FASA 6 — Report WhatsApp
```
1. Template utility dynoads_daily_report (guna skill whatsapp-cloud-api). Hantar 8:00 pagi.
2. Kandungan: belanja semalam, lead, kos/lead, iklan terbaik, tindakan app semalam, satu cadangan.
3. Notifikasi segera untuk tindakan besar (pause / campaign baru) — jangan tunggu pagi.
4. Kegagalan hantar direkod, tidak menghentikan pusingan automasi.
```

---

# FASA 7 — Multi-user + App Review pusingan 1
```
1. Auth Breeze + OTP telefon (OtpService).
2. Facebook Login for Business via Socialite. Scope pusingan 1 SAHAJA:
   ads_management, ads_read, business_management, pages_show_list,
   pages_read_engagement, pages_manage_ads.
   (pages_read_engagement sudah cukup untuk senarai posting Fasa 1 — tiada tambahan.)
3. Token long-lived, encrypted dalam fb_connections.
4. MetaAdsService ambil token/account/page dari FbConnection user; .env kekal fallback pemilik.
5. Semua model ada user_id + policy isolation.
6. PDPA (spec v0.1 §9): consent berasingan, Privacy Notice BM, skrin "Data Saya",
   endpoint POST /meta/data-deletion (verify signed_request), retention 90 hari.
7. Peringatan token luput 7 hari awal.
8. Test: isolation antara user; data deletion callback.
```

---

# FASA 8 — Jual
```
1. Pelan Percuma / Peniaga RM49 / Pro RM129 (had ikut spec §9). toyyibPay FPX (skill toyyibpay).
2. Kuota dikuatkuasakan: campaign aktif, creative, dan caption AI sebulan.
   PERHATIAN: model harga asal membezakan pelan dengan kuota latar AI (5/40/150 sebulan).
   Latar AI sudah dibatalkan, jadi pembeza itu tiada. Pelan perlukan sebab baharu untuk
   orang bayar Pro — cadangan: bilangan campaign aktif, kekerapan report, atau sokongan
   video. BELUM DIPUTUSKAN oleh pemilik; jangan bina harga sebelum ia diputuskan.
3. Landing page (skill seo-landing-page). Onboarding 3 skrin: app optimize, tak jamin lead.
4. Admin ringkas: senarai user, pelan, status FB connection, penggunaan AI.
```

---

# FASA 9 — Posting organik + App Review pusingan 2
```
1. scheduled_posts + kalendar kandungan (2/3/5 kali seminggu).
2. Terbit FB Page (/{page-id}/photos) dan IG (/media → /media_publish).
3. Skrin approve senarai — tick banyak sekali gus, bukan satu-satu.
4. Permission pusingan 2: pages_manage_posts, instagram_basic, instagram_content_publish.
5. Posting yang app terbitkan sendiri kena boleh terus dipilih dalam Fasa 1 untuk di-ads-kan.
6. Test: penjadualan, kegagalan terbit tidak hilang senyap.
```

---

## Cara guna fail ini
1. Buka Claude Code pada repo → paste prompt SATU fasa sahaja.
2. Bila DoD fasa tu lulus dan dah dipakai, baru fasa seterusnya.
3. Business Verification Meta dimulakan sekarang, selari dengan coding — ia ambil berminggu.
