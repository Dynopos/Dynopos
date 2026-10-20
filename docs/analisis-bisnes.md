# Analisis Bisnes — Dyno Ads

Dokumen ini menjawab satu soalan yang CLAUDE.md tandakan **BELUM DIPUTUSKAN**:
apa yang membezakan pelan berbayar selepas latar AI dibatalkan, dan berapa patut
dicaj.

Semua angka di bawah datang dari kod dan dari 150+ campaign sebenar DynoPOS yang
dicatat dalam CLAUDE.md. Di mana saya menganggar, ia ditandakan.

---

## 1. Apa yang sebenarnya dijual

Bukan perisian membuat iklan. Meta Ads Manager percuma, dan ia lebih berkuasa
daripada app ini.

Yang dijual ialah **tiga perkara yang peniaga tidak ada**:

| | Nilai sebenar |
|---|---|
| Tetapan terbukti | CBO, `CONVERSATIONS`, umur 25–65, `locales=[41]`, geo Malaysia tolak tiga negeri. Ini datang dari 150+ campaign berbayar, bukan dari tutorial YouTube |
| Copy Melayu yang menjual | Lima sudut berbeza, dijana automatik. Kebanyakan peniaga menulis satu caption dan berhenti |
| Split test automatik | Satu media satu campaign, bandingkan kos/lead, kekalkan yang menang |

Tetapi nilai yang paling besar tidak tersenarai di atas, dan ia hanya jelas
selepas tiga jam cuba menyambung akaun Meta dalam sesi pembangunan:

**Peniaga tidak perlu faham Ads Manager langsung.**

Itu yang mahal. Itu sebabnya app sebegini berharga ribuan ringgit — bukan kerana
kodnya sukar, tetapi kerana plumbing antara peniaga dan Meta itu menyakitkan, dan
tiada siapa boleh mengautomasikannya untuk anda.

---

## 2. Kos melayan seorang pelanggan

### Yang hampir percuma

| Item | Kos |
|---|---|
| Panggilan API Meta | Percuma |
| Caption AI | Satu panggilan setiap set iklan, model kecil. Sen, bukan ringgit — semak kadar semasa pembekal sebelum mengira dengan tepat |
| Gambar | 4 × ~250KB = ~1MB setiap set iklan |
| Pelayan | Kos tetap, tidak bergerak dengan bilangan pelanggan sehingga beribu |

### Yang tidak percuma, dan tiada had

**Video.** `config/dynoads.php` membenarkan 100MB setiap fail, empat fail setiap
set iklan — 400MB untuk satu set iklan pada kes terburuk. Realistiknya video
telefon 30 saat ~30MB, jadi ~120MB setiap set iklan.

Dan **tiada job yang memadam media lama**. Saya semak: tiada `prune`, tiada
`cleanup`, tiada jadual. Setiap video yang dimuat naik kekal di cakera selamanya.

Seorang peniaga yang membuat 4 set iklan sebulan dengan video:

```
4 set × 120MB = ~500MB sebulan = ~6GB setahun, seorang
100 peniaga   = ~600GB setahun, dan ia tidak pernah susut
```

Ini bukan krisis hari ni. Tetapi ia satu-satunya kos yang **berkembang tanpa
had**, dan ia menjawab soalan pembeza pelan.

> **Tindakan:** tambah job pembersihan — padam fail media set iklan yang sudah
> `done` melebihi tempoh retention (90 hari, sudah ada dalam
> `config/dynoads.privacy.retention_days`). Ini juga kewajipan PDPA Prinsip
> Penyimpanan, bukan sekadar penjimatan kos.

---

## 3. Berapa patut dicaj

### Jangkar: berapa peniaga sudah belanja

Bajet lalai dalam kod ialah **RM37/hari** — RM1,110 sebulan. Julatnya RM10–200
sehari, jadi RM300 hingga RM6,000 sebulan.

| Harga | % daripada belanja iklan RM1,110 |
|---|---|
| RM49 | 4.4% |
| RM129 | 11.6% |

Agensi iklan biasanya mengambil 15–20% daripada belanja iklan, atau retainer
RM500–1,500 sebulan. **RM49 jauh lebih murah daripada alternatif.**

### Nilai yang boleh dibuktikan

Kos/lead sebenar DynoPOS: RM10–15. Ambil peniaga pada RM1,110 sebulan:

```
Pada RM15/lead  →  74 lead sebulan
Pada RM12/lead  →  92 lead sebulan
```

Kalau split test menurunkan kos/lead dari RM15 ke RM12 — bukan mustahil, itu
memang gunanya membandingkan empat creative — peniaga dapat **18 lead tambahan
sebulan** pada belanja yang sama. Atau menjimatkan RM222 untuk jumlah lead yang
sama.

**RM49 untuk menjimatkan RM222 ialah cerita yang senang dijual** — tetapi hanya
kalau split test betul-betul memberi hasil itu. Itu janji yang perlu dibuktikan
dengan data pelanggan perintis sebelum dijadikan bahan pemasaran.

---

## 4. Apa yang membezakan pelan — keputusan yang tergantung

Model asal membezakan pelan dengan kuota latar AI (5/40/150 sebulan). Latar AI
dibuang, jadi pembeza itu tiada.

### Pilihan

| Paksi | Kekuatan | Kelemahan |
|---|---|---|
| **Campaign aktif serentak** | Terus berkait dengan berapa peniaga belanja, jadi terus berkait dengan kemampuan bayar. Senang difahami | Peniaga boleh pause-and-swap untuk mengelak had |
| **Video** | Satu-satunya kos berubah yang sebenar. Format premium yang Meta sendiri galakkan | Menghadkan video bermakna menghadkan ciri terbaik app |
| **Kekerapan report** | Murah untuk diberi | Pembeza yang lemah — orang tidak bayar untuk report |
| **Bilangan Page/akaun** | Sesuai untuk agensi kecil | Pasaran kecil pada peringkat ini |

### Cadangan

**Campaign aktif sebagai paksi utama, video sebagai paksi kedua.**

| | Percuma | Peniaga | Pro |
|---|---|---|---|
| Campaign aktif serentak | 1 | 4 | 12 |
| Video | ✗ gambar sahaja | ✓ | ✓ |
| Simpanan media | 30 hari | 90 hari | 1 tahun |
| Report WhatsApp | ✗ | mingguan | harian |

Sebabnya: campaign aktif ialah perkara yang peniaga sendiri fikir dalam kepalanya
("berapa iklan boleh jalan serentak"), ia berkadar dengan belanja iklan dia, dan
video — satu-satunya kos sebenar kita — jatuh di belakang dinding berbayar secara
semula jadi.

Pelan Percuma dengan gambar sahaja juga ada logik produk: CLAUDE.md sudah
menyatakan guna posting sedia ada tidak memakan kuota. Percuma = cuba dengan satu
iklan, buktikan ia berfungsi, kemudian bayar bila nak lebih.

**Angka dalam jadual di atas adalah cadangan, bukan keputusan.** Ia perlu diuji
terhadap pelanggan sebenar.

---

## 5. Halangan sebenar: harga mesti sepadan dengan cara onboarding

Ini bahagian paling penting dalam dokumen ni, dan ia bukan tentang harga.

**Sebelum App Review lulus, tiada butang "Sambung Facebook".** Setiap pelanggan
perlu disambungkan secara manual — oleh pemilik app, melalui skrin `/sambung`.

Anggarkan 30–60 minit setiap pelanggan: terangkan, tolong cari ID, selesaikan
masalah Business Manager, uji satu iklan. Itu anggaran, dan sesi pembangunan
menunjukkan ia mungkin lebih lama.

```
RM49 sebulan, 45 minit onboarding
→ satu bulan penuh hasil habis hanya untuk menyediakan akaun, sebelum sokongan
→ 10 pelanggan  = ~8 jam
→ 100 pelanggan = ~75 jam, dan anda hanya seorang
```

**Harga self-serve dengan onboarding manual adalah rugi.** Itu bukan pendapat; ia
aritmetik.

### Jadi: dua fasa, dua model

**Fasa perintis (sekarang → App Review lulus)**

- 5–10 peniaga sahaja, dipilih sendiri
- Anda sambungkan akaun mereka — mereka tidak nampak borang token langsung
- Harga sebagai **perkhidmatan**, bukan langganan perisian: yuran penyediaan
  sekali (cth RM300) + RM99–200 sebulan, atau percuma sepenuhnya kalau matlamatnya
  testimoni dan data
- **Sengaja tidak boleh skala.** Matlamatnya bukan hasil — ia bukti bahawa split
  test benar-benar menurunkan kos/lead, dan ayat sebenar daripada peniaga sebenar

**Fasa self-serve (selepas App Review)**

- Butang Sambung Facebook menggantikan borang token
- Onboarding jatuh dari 45 minit ke 2 minit
- Barulah RM49/RM129 masuk akal

Jangan lancarkan harga self-serve sebelum onboarding self-serve wujud. Anda akan
mengikat diri pada harga yang tidak menampung kos melayan pelanggan.

---

## 6. Risiko yang menggantung di atas semua ini

Setiap perkara dalam dokumen ini menganggap satu perkara yang **belum selesai**:
identiti Meta yang bersih atas nama syarikat.

Setakat 17 Okt 2026:

- Akaun FB pemilik disekat daripada beriklan
- Akaun iklan yang digunakan milik ahli keluarga
- Business Verification belum dimulakan
- App Review belum boleh dimulakan

Tiada harga, tiada pelan, dan tiada pelanggan berbayar bermakna apa-apa sehingga
Business Portfolio atas nama syarikat (SSM sudah ada) wujud dan disahkan.

**Itu jalan kritikal. Semua yang lain boleh menunggu; itu tidak.**

---

## 7. Ringkasan tindakan

| # | Tindakan | Siapa | Bila |
|---|---|---|---|
| 1 | Business Portfolio atas nama syarikat + mula Business Verification | Pemilik | Sekarang — ia ambil berminggu |
| 2 | Pilih 5 peniaga perintis, sambungkan akaun mereka sendiri | Pemilik | Selari dengan #1 |
| 3 | Rekod kos/lead sebelum dan selepas split test bagi setiap perintis | Pemilik | Sepanjang fasa perintis |
| 4 | Job pembersihan media selepas retention 90 hari | Kod | Sebelum pelanggan video pertama |
| 5 | Kuota campaign aktif + video | Kod | Selepas #2 memberi data |
| 6 | Harga self-serve RM49/RM129 | Pemilik | **Hanya selepas App Review lulus** |

Langkah 3 ialah yang paling kerap dilangkau dan paling berharga. Tanpa angka
sebelum-dan-selepas daripada peniaga sebenar, harga RM49 hanyalah tekaan, dan
pemasaran tiada apa-apa untuk dikatakan selain janji.
