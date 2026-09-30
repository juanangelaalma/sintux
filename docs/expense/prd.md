# Expenses Module (Biaya) — Spesifikasi & Instruksi untuk Agentic AI

> Dokumen ini adalah **sumber kebenaran tunggal** untuk membangun module Biaya (Expenses) hasil reverse engineering fitur Biaya di Mekari Jurnal.
> Baca seluruh dokumen sebelum menulis kode. Ikuti urutan fase di Bagian 3.

---

## 0. Aturan main untuk agent

### 0.1 Label kepastian
Setiap klaim dalam dokumen ini diberi label. Patuhi cara memperlakukannya:

| Label | Arti | Perlakuan agent |
|---|---|---|
| `[CONFIRMED]` | Tertulis di dokumentasi Jurnal atau terlihat di screenshot | Implementasikan persis |
| `[INFERRED]` | Kesimpulan dari prinsip akuntansi atau pola UI, belum diverifikasi | Implementasikan, tandai dengan komentar `// INFERRED: BR-xx`, dan masukkan ke laporan akhir fase |
| `[OPEN]` | Belum diketahui atau saling bertentangan | **Jangan menebak.** Berhenti, catat di `docs/expenses/OPEN_QUESTIONS.md`, tanyakan ke manusia |

### 0.2 Prinsip kerja
1. **Temukan stack dulu (Fase 0).** Dokumen ini tidak mengasumsikan bahasa, framework, atau database. Ikuti konvensi repositori yang sudah ada.
2. **Jangan mengarang fitur.** Jika tidak ada di dokumen ini, jangan dibuat. Tulis sebagai saran di `OPEN_QUESTIONS.md`.
3. **Uang tidak pernah `float`.** Gunakan tipe desimal (lihat BR-01).
4. **Setiap aturan bisnis (BR-xx) harus punya minimal satu test** yang menyebut ID-nya di nama test.
5. **Satu fase = satu PR/commit set** yang bisa di-review dan berjalan sendiri. Jangan mencampur fase.
6. **Jangan mengubah modul lain** (CoA, Kontak, Pajak, Jurnal umum) kecuali lewat antarmuka publik yang sudah ada. Jika perlu perubahan, tanyakan dulu.
7. **Bahasa UI: Indonesia.** Identifier kode, nama tabel, dan nama endpoint: Inggris.
8. Semua teks UI: sentence case ("Buat biaya baru"), tanpa tanda seru, kalimat error menyebut apa yang terjadi lalu apa yang harus dilakukan.

### 0.3 Definition of Done (berlaku untuk setiap fase)
- [ ] Semua item fase terimplementasi dan sesuai label kepastian
- [ ] Test untuk setiap BR yang disentuh fase itu lulus
- [ ] Lint, typecheck, dan migrasi (up dan down) bersih
- [ ] `docs/expenses/PHASE_N_REPORT.md` ditulis: apa yang dibuat, daftar `[INFERRED]` yang dipakai, daftar `[OPEN]` yang ditemui
- [ ] Tidak ada TODO tanpa referensi ke ID di OPEN_QUESTIONS

---

## 1. Sumber referensi

| # | Sumber | Isi |
|---|---|---|
| S1 | https://help-center.jurnal.id/hc/id/articles/4473887616409-Sekilas-Mengenai-Menu-Biaya | Halaman utama, 3 kartu filter, pencarian |
| S2 | https://help-center.jurnal.id/hc/id/articles/4473886044569-Bagaimana-Cara-Membuat-Biaya-Baru | 17 kolom formulir, bayar langsung vs bayar nanti, pelunasan |
| S3 | https://help-center.jurnal.id/hc/id/articles/4416741450777-Bagaimana-Cara-Melihat-Detail-Biaya-Mengubah-dan-Menghapus-Biaya | Detail, ubah, hapus, pembatasan, akses kontrol |
| S4 | https://help-center.jurnal.id/hc/id/articles/4473931413657-Bagaimana-Cara-Menyetujui-Transaksi-Draft-Biaya | Approval draft, komentar, log |
| S5 | Screenshot form "Buat Biaya" | Layout asli formulir |
| S6 | Screenshot halaman "Pengeluaran" | Layout asli daftar biaya |

Artikel yang **belum** dibaca dan relevan (jangan berasumsi isinya): aturan approval, impor biaya, pengaturan penomoran transaksi, pembatasan CoA per peran, custom role.

---

## 2. Glosarium

| Istilah | Arti |
|---|---|
| Biaya / Beban | Pengeluaran operasional, dicatat sebagai akun kategori Beban |
| Bayar langsung | Biaya diakui dan dilunasi di transaksi yang sama |
| Bayar nanti (pay later) | Biaya diakui sekarang, dilunasi kemudian. Membentuk hutang |
| Biaya terutang | Biaya dengan Bayar nanti yang belum lunas |
| Pelunasan / Bayar tagihan | Transaksi pembayaran atas biaya terutang |
| Pemotongan | Nilai yang mengurangi tagihan, ditampung di akun tertentu |
| DPP | Dasar pengenaan pajak |
| Draft | Transaksi yang menunggu approval, belum memengaruhi laporan keuangan |
| Kunci periode / tutup buku | Periode yang transaksinya tidak boleh diubah |
| Rekonsiliasi | Pencocokan transaksi dengan mutasi bank |

---

## 3. Fase pengerjaan

| Fase | Nama | Isi utama |
|---|---|---|
| **0** | Discovery | Deteksi stack, konvensi, modul dependensi, rencana |
| **1** | Inti | Model data, CRUD biaya, hitung pajak/pemotongan, jurnal otomatis, daftar + kartu ringkasan, form 17 kolom |
| **2** | Pelunasan & pembatasan | Bayar tagihan, cetak slip, aturan blokir ubah/hapus, impor (setelah spesifikasi impor dibaca) |
| **3** | Approval & akses | Draft, approver, komentar, log, custom role, pembatasan CoA, multi-currency |
| **4** | Integrasi | Mekari Expense, Mekari Pay, mobile |

Jangan mulai fase N+1 sebelum Definition of Done fase N terpenuhi dan manusia menyetujui laporan fasenya.

### Fase 0 — Discovery (wajib, tanpa menulis fitur)
1. Identifikasi bahasa, framework, ORM, sistem migrasi, test runner, pola auth, pola permission, pola i18n.
2. Cari dan catat lokasi modul yang menjadi dependensi: **Chart of Accounts, Kontak, Pajak, Tag, Cara Pembayaran, Mata uang, Penomoran transaksi, Jurnal umum (general ledger), Periode/tutup buku, Rekonsiliasi, Lampiran/file storage**.
3. Untuk setiap dependensi tulis: ada / tidak ada / sebagian, dan path-nya.
4. Tulis `docs/expenses/PHASE_0_REPORT.md` berisi temuan dan usulan struktur direktori.
5. **Berhenti dan minta persetujuan manusia** jika ada dependensi yang tidak ada.

---

## 4. Model data

Nama tabel dan tipe bersifat **usulan**; sesuaikan dengan konvensi repositori. Semua kolom uang bertipe desimal.

### 4.1 `expenses` (header)

| Kolom | Tipe | Wajib | Catatan |
|---|---|---|---|
| id | pk | ya | |
| company_id | fk | ya | Multi-tenant |
| number | string | ya | Unik per company. Otomatis jika kosong (BR-04) |
| transaction_date | date | ya | |
| pay_from_account_id | fk CoA | kondisional | Kas/bank. Lihat OPEN Q-03 untuk kasus Bayar nanti |
| is_pay_later | bool | ya | default false |
| contact_id | fk kontak | tidak | Penerima |
| billing_address | text | tidak | |
| payment_method_id | fk | kondisional | Default "Cek & Giro" `[CONFIRMED S5]` |
| currency_code | string | ya | default mata uang dasar. Tampil di UI hanya jika multi-currency aktif |
| is_tax_inclusive | bool | ya | default dari pengaturan perusahaan (BR-06) |
| withholding_type | enum(`percent`,`amount`) | tidak | |
| withholding_value | decimal | tidak | |
| withholding_account_id | fk CoA | tidak | Wajib jika withholding_value > 0 |
| memo | text | tidak | Internal, tidak dicetak di PDF `[CONFIRMED]` |
| subtotal | decimal | ya | Disimpan hasil hitung |
| tax_total | decimal | ya | |
| withholding_total | decimal | ya | |
| grand_total | decimal | ya | |
| amount_paid | decimal | ya | default 0 |
| status | enum | ya | Lihat 4.4 |
| source | enum(`manual`,`import`,`mekari_expense`,`mekari_pay`) | ya | default `manual` |
| approval_status | enum(`none`,`pending`,`approved`) | ya | Fase 3 |
| created_by | fk user | ya | |
| created_at, updated_at, deleted_at | timestamp | ya | |

Indeks: `(company_id, number)` unik, `(company_id, transaction_date)`, `(company_id, status)`, `(company_id, contact_id)`.

### 4.2 `expense_lines`

| Kolom | Tipe | Wajib | Catatan |
|---|---|---|---|
| id, expense_id | | ya | |
| position | int | ya | Urutan tampil |
| account_id | fk CoA | ya | Hanya kategori: Harga Pokok Penjualan, Beban, Beban Lainnya (BR-02) |
| description | text | tidak | |
| tax_id | fk pajak | tidak | |
| amount | decimal | ya | Nilai sebagaimana diinput user |
| amount_before_tax | decimal | ya | Hasil hitung (presisi internal 6 desimal) |
| tax_amount | decimal | ya | |

### 4.3 Tabel pendukung

- `expense_tags(expense_id, tag_id)` — many-to-many
- `expense_attachments(id, expense_id, file_ref, filename, size_bytes, mime)` — maks 10 MB per file `[CONFIRMED]`
- `expense_payments(id, company_id, payment_date, account_id, payment_method_id, contact_id, total, created_by)` dan `expense_payment_items(payment_id, expense_id, amount)` — satu pembayaran boleh mencakup banyak biaya dengan **contact yang sama** (BR-12)
- Fase 3: `expense_approval_rules`, `expense_approval_logs(expense_id, actor_id, action, at)`, `expense_comments(expense_id, author_id, body(<=255), created_at)`

### 4.4 Status

Terlihat di screenshot: `Closed` `[CONFIRMED S6]`. Nilai lain belum terlihat.

| Status internal | Label UI | Label | Aturan |
|---|---|---|---|
| `draft` | (di tab Membutuhkan Persetujuan) | INFERRED | approval_status = pending |
| `open` | Open | INFERRED | belum lunas, sisa > 0 |
| `closed` | Closed | CONFIRMED | lunas, sisa = 0 |
| `overdue` | Overdue | OPEN Q-05 | Ada jatuh tempo atau tidak? Form tidak punya kolom jatuh tempo |

Sisa tagihan = `grand_total - amount_paid`.

---

## 5. Aturan bisnis

### Perhitungan
- **BR-01** `[INFERRED]` Semua uang: tipe desimal. Presisi internal 6 desimal untuk DPP dan pajak (contoh sumber: `90.090,090090`), tampilan 2 desimal. Aturan pembulatan agar jurnal seimbang: lihat OPEN Q-06.
- **BR-02** `[CONFIRMED S2]` Pilihan akun biaya dibatasi kategori CoA: Harga Pokok Penjualan, Beban, Beban Lainnya.
- **BR-03** `[CONFIRMED S2]` Satu transaksi boleh punya banyak baris akun.
- **BR-04** `[CONFIRMED S2]` Jika `number` kosong, sistem mengisi otomatis mulai dari **10001**. Format custom didukung lewat pengaturan penomoran (baca artikel penomoran sebelum implementasi format custom; sampai itu, hanya format default).
- **BR-05** `[CONFIRMED S2]` Pemotongan bertipe persen dihitung dari **nilai sebelum pajak**. Tipe nominal dipakai apa adanya. Akun penampung wajib dipilih.
- **BR-06** `[CONFIRMED S2]` Toggle "Harga termasuk pajak": nominal input sudah termasuk pajak sehingga DPP = nominal dipecah. Pengaturan perusahaan "Termasuk Pajak" membuat toggle default aktif dan berlaku untuk hasil impor.
  - Contoh sumber: Rp 100.000, "PPN 12% dengan pengali 11/12" menghasilkan DPP Rp 90.090,090090 dan pajak Rp 9.909,909909. Ini setara `DPP = total / 1.11`.
  - Tidak tersedia untuk tipe pajak pemotongan.
  - **Kontradiksi di sumber:** artikel yang sama menyatakan fitur ini belum bisa dipakai pada pajak dengan pengali 11/12. Lihat OPEN Q-01.
- **BR-07** `[INFERRED]` `grand_total = subtotal + tax_total - withholding_total`. Di layar S5 terlihat SubTotal, Total, link pemotongan, lalu Total akhir; tampilan dua "Total" mengikuti S5 dan maknanya dikonfirmasi di OPEN Q-04.

### Bayar langsung vs bayar nanti
- **BR-08** `[CONFIRMED S2]` Bayar langsung: `amount_paid = grand_total`, status `closed`.
- **BR-09** `[CONFIRMED S2]` Bayar nanti: jurnal mengakui **Debit Beban, Kredit Hutang**. Status `open` sampai dilunasi.
- **BR-10** `[CONFIRMED S2]` Tanggal transaksi sebaiknya sama dengan tanggal pembayaran agar cocok dengan mutasi rekening (hanya panduan, tidak divalidasi).

### Pelunasan
- **BR-11** `[CONFIRMED S2]` Pelunasan lewat detail biaya, Tindakan, Bayar Tagihan. Pilih akun kas/bank dan nomor biaya terutang.
- **BR-12** `[CONFIRMED S2]` Satu pembayaran boleh melunasi banyak biaya terutang **selama Penerima sama**.
- **BR-13** `[INFERRED]` Pelunasan sebagian diizinkan (didukung oleh BR-15). Konfirmasi di OPEN Q-07.

### Ubah dan hapus
- **BR-14** `[CONFIRMED S3]` **Tidak boleh diubah** jika: sudah direkonsiliasi; tanggal transaksi di periode tutup buku/kunci; `source = mekari_expense`.
- **BR-15** `[CONFIRMED S3]` Nominal boleh diubah selama tidak menjadi lebih kecil dari total pelunasan yang sudah ada.
- **BR-16** `[CONFIRMED S3]` Pada biaya Bayar nanti yang sudah punya pelunasan, `contact_id` dan `currency_code` **terkunci**.
- **BR-17** `[CONFIRMED S3]` **Tidak boleh dihapus** jika: sudah direkonsiliasi; periode terkunci; dibayar via Mekari Pay; `source = mekari_expense`.
- **BR-18** `[CONFIRMED S3]` Menghapus biaya yang punya pelunasan ikut menghapus transaksi pelunasannya.
- **BR-19** `[CONFIRMED S3]` Hapus massal: centang beberapa nomor, tekan Hapus, konfirmasi "Yes". Terapkan BR-17 per item dan laporkan item yang gagal beserta alasannya, jangan membatalkan seluruh batch. Perilaku batch aslinya `[OPEN Q-08]`.
- **BR-20** `[CONFIRMED S3]` Ada pengaturan agar biaya hanya bisa diakses pembuatnya (Pengaturan, Pengaturan Pengguna).

### Approval (Fase 3)
- **BR-21** `[CONFIRMED S4]` Draft harus disetujui approver sebelum menjadi transaksi. Draft **tidak memengaruhi laporan keuangan**.
- **BR-22** `[CONFIRMED S4]` Approver boleh mengubah draft (tetap draft) atau menolak lewat Hapus.
- **BR-23** `[CONFIRMED S4]` Draft yang diubah dipetakan ulang ke aturan approval yang paling sesuai.
- **BR-24** `[CONFIRMED S4]` Komentar: maks 255 karakter, tanpa emoji, tanpa gambar, tidak bisa diedit atau dihapus, maks 50 per transaksi, hanya bisa dikirim saat status draft.
- **BR-25** `[CONFIRMED S4]` Setelah disetujui, transaksi pindah ke tab Biaya dan tersedia approval log.
- **BR-26** `[CONFIRMED S4]` Tutup buku tetap boleh jika masih ada draft, tetapi user diberi peringatan. Draft tidak bisa disetujui setelah periodenya ditutup.
- **BR-27** `[CONFIRMED S4]` Approver menerima email berisi tautan "Lihat transaksi".
- **BR-28** `[CONFIRMED S4]` Fitur approval hanya untuk paket tertentu (Plus dan 360). Implementasikan sebagai feature flag, bukan hard-code.

---

## 6. Posting jurnal `[INFERRED]`

Seluruh bagian ini adalah kesimpulan akuntansi, bukan kutipan sumber, kecuali BR-09. Validasi dengan manusia sebelum Fase 1 dianggap selesai.

**Bayar langsung**
```
Dr  Akun biaya (per baris)            amount_before_tax
Dr  PPN Masukan (akun pajak)          tax_amount
    Cr  Kas/Bank (pay_from)               grand_total
    Cr  Akun penampung pemotongan         withholding_total
```
(Jika ada pemotongan, kredit Kas/Bank dikurangi sebesar pemotongan sehingga jurnal tetap seimbang.)

**Bayar nanti**
```
Dr  Akun biaya, Dr PPN Masukan
    Cr  Hutang usaha                      grand_total  (dikurangi pemotongan, lihat Q-04)
    Cr  Akun penampung pemotongan         withholding_total
```

**Pelunasan**
```
Dr  Hutang usaha                          amount
    Cr  Kas/Bank                              amount
```

Aturan umum: jurnal wajib seimbang, dibuat dalam satu database transaction bersama penyimpanan biaya, dan dibalik atau dihapus saat biaya dihapus (BR-18).

---

## 7. Spesifikasi UI

Layout mengikuti screenshot S5 dan S6. Wireframe HTML terlampir di percakapan sebagai referensi visual; jika tersedia sebagai file, taruh di `docs/expenses/wireframes/`.

### 7.1 Halaman daftar (`/expenses`) `[CONFIRMED S6]`

**Header:** breadcrumb kecil "Biaya", judul "Pengeluaran", tombol kanan atas "Buat biaya baru".

**Tiga kartu ringkasan** (header biru muda, badge jumlah transaksi di kanan, isi: label "Total" dan nominal):

| Kartu | Isi | Klik |
|---|---|---|
| Total biaya bulan ini (dalam IDR) | Biaya bulan berjalan yang **lunas** | Memfilter daftar |
| Biaya 30 hari terakhir (dalam IDR) | Biaya 30 hari terakhir yang **lunas** | Memfilter daftar |
| Biaya belum dibayar (dalam IDR) | Biaya belum lunas, **semua periode** | Memfilter daftar |

Catatan kecil di bawah kartu: "Saldo adalah untuk semua jangka waktu, kecuali ada pernyataan lain".

**Panel "Daftar biaya":** tombol Impor (Fase 2), kolom pencarian (nomor biaya, kategori akun biaya, tag `[CONFIRMED S1]`), tab **Biaya** dan **Membutuhkan persetujuan** dengan badge jumlah (tab kedua hanya jika approval aktif, Fase 3).

**Tabel:**

| Kolom | Rata | Perilaku |
|---|---|---|
| Checkbox | kiri | Pilih massal; header checkbox = pilih semua di halaman |
| Tanggal | kiri | Format `DD-MM-YYYY`, urut default terbaru di atas |
| Nomor | kiri | Tautan ke detail |
| Kategori | kiri | Nama akun; jika baris memakai lebih dari satu akun tampilkan `-Terbagi-` |
| Penerima | kiri | Nama kontak, boleh kosong |
| Status | kiri | Lihat 4.4 |
| Sisa tagihan (dalam IDR) | kanan | Format `Rp. 0,00` |
| Total (dalam IDR) | kanan | Format `Rp. 114.583,33` |
| Tags | kiri | Daftar tag |

Header kolom berupa tautan biru, artinya bisa diurutkan `[INFERRED]`. Paginasi tidak terlihat di screenshot: `[OPEN Q-09]`.

Format angka Indonesia: pemisah ribuan titik, desimal koma, prefiks `Rp. `.

**Empty state:** tanpa data tampilkan ajakan "Catat biaya pertama Anda" dengan tombol "Buat biaya baru" `[INFERRED]`.

### 7.2 Formulir buat/ubah biaya `[CONFIRMED S2, S5]`

Urutan visual dari atas ke bawah:

1. **Header halaman:** judul "Buat biaya", tombol "Lihat panduan" kanan atas.
2. **Banner biru:** kolom 1 dan 2 di kiri, **Total live** besar di kanan (berubah saat baris diisi).
3. **Baris 5 kolom:** kolom 3, 4, 5, 6, 7.
4. **Alamat penagihan (8)** di bawah Penerima, lebar kolom kiri.
5. **Mata uang (9)** hanya jika multi-currency aktif.
6. **Toggle Harga termasuk pajak (10)** rata kanan di atas tabel.
7. **Tabel baris akun:** kolom 11 sampai 14, dua baris kosong default, ikon minus untuk hapus baris, tombol "Tambah data".
8. **Bawah kiri:** Memo (16), Lampiran (17).
9. **Bawah kanan:** Subtotal, Total, link "Masukkan jumlah pemotongan" (15), Total akhir.
10. **Tombol:** Batal (merah), Buat biaya baru (hijau) dengan dropdown berisi "Buat & baru" `[INFERRED dari S2]`.

**Spesifikasi 17 kolom**

| # | Label | Tipe kontrol | Wajib | Default / perilaku |
|---|---|---|---|---|
| 1 | Bayar dari | Select akun kas/bank (bisa dikosongkan) | ya, kecuali OPEN Q-03 | Default `(1-10001) - Kas (Cash & Bank)` `[CONFIRMED S5]` |
| 2 | Bayar nanti | Checkbox | tidak | false. Jika aktif, jurnal mengikuti BR-09 |
| 3 | Penerima | Select kontak | tidak | Opsi: kontak tipe supplier, karyawan, lainnya |
| 4 | Tgl transaksi | Date picker | ya | Hari ini. Tampil `DD/MM/YYYY` di form |
| 5 | Cara pembayaran | Select + tambah baru (ketik langsung) | ya | Default `Cek & Giro`. Bawaan: Kas Tunai, Cek & Giro, Transfer Bank, Kartu Kredit |
| 6 | No biaya | Text | tidak | Placeholder `[Auto]`, ikon gear menuju pengaturan penomoran |
| 7 | Tag | Multi-select + tambah baru (ketik langsung) | tidak | |
| 8 | Alamat penagihan | Textarea | tidak | Alamat penerima |
| 9 | Mata uang | Select | ya jika tampil | Muncul hanya jika multi-currency aktif |
| 10 | Harga termasuk pajak | Toggle | ya | Default dari pengaturan perusahaan; false jika tidak diatur |
| 11 | Akun biaya | Select CoA (dibatasi BR-02) | ya per baris | Kosong |
| 12 | Deskripsi | Textarea per baris | tidak | |
| 13 | Pajak | Select | tidak | |
| 14 | Jumlah | Input uang | ya per baris | Placeholder `Rp. 0,00` |
| 15 | Masukkan jumlah pemotongan | Link yang membuka: tipe (% atau nominal), nilai, akun penampung | tidak | Tertutup default `[INFERRED bentuk terbuka]` |
| 16 | Memo | Textarea | tidak | Catatan internal |
| 17 | Lampiran | Dropzone "Tarik file ke sini, atau pilih file" | tidak | Maks 10 MB per file |

**Validasi:**
- Minimal 1 baris akun terisi lengkap (akun dan jumlah > 0) `[INFERRED]`
- Jika pemotongan diisi, akun penampung wajib
- Persen pemotongan antara 0 dan 100 `[INFERRED]`
- Nomor biaya harus unik per company (pesan: "Nomor biaya sudah dipakai. Gunakan nomor lain.")
- Tanggal tidak boleh di periode terkunci (pesan: "Tanggal ini berada di periode yang sudah dikunci. Pilih tanggal lain.")

**Tombol simpan:** "Buat biaya baru" menyimpan dan membuka detail; "Buat & baru" menyimpan lalu membuka form kosong `[CONFIRMED S2]`; "Batal" kembali tanpa menyimpan.

### 7.3 Halaman detail `[CONFIRMED S3, sebagian INFERRED]`
Tampilkan seluruh data header dan baris, status, riwayat pelunasan. Aksi: **Ubah**, **Hapus**, **Tindakan** menu berisi **Bayar Tagihan** (hanya jika bayar nanti dan belum lunas), **Cetak & Lihat** menu berisi **Preview Slip Pengeluaran** yang menghasilkan PDF (unduh dan cetak). Aksi yang dilarang BR-14 dan BR-17 harus tetap terlihat tetapi menampilkan alasan saat dipakai, jangan disembunyikan diam-diam.

### 7.4 Tab "Membutuhkan persetujuan" (Fase 3)
Daftar draft yang menunggu approver. Aksi per transaksi: Ubah, Hapus (tolak), ikon komentar, ikon centang (setujui), ikon jam (approval log).

---

## 8. Usulan API

Sesuaikan prefiks dan gaya dengan repositori. Semua endpoint wajib menegakkan `company_id` dari sesi, bukan dari body.

| Method | Path | Tujuan |
|---|---|---|
| GET | `/expenses/summary` | Data 3 kartu (nominal dan jumlah) |
| GET | `/expenses` | Daftar. Query: `filter=this_month\|last_30_days\|unpaid`, `q`, `tab`, `sort`, `page` |
| POST | `/expenses` | Buat biaya (dengan `create_and_new` opsional di klien saja) |
| GET | `/expenses/:id` | Detail |
| PATCH | `/expenses/:id` | Ubah (tegakkan BR-14 sampai BR-16) |
| DELETE | `/expenses/:id` | Hapus satu (BR-17, BR-18) |
| POST | `/expenses/bulk-delete` | Hapus massal (BR-19). Respons: daftar sukses dan gagal beserta alasan |
| POST | `/expenses/:id/payments` atau `/expense-payments` | Bayar tagihan (BR-11 sampai BR-13) |
| GET | `/expenses/:id/slip.pdf` | Slip pengeluaran |
| POST | `/expenses/calculate` | Hitung pratinjau total (dipakai form untuk Total live) tanpa menyimpan |
| Fase 3 | `/expenses/:id/approve`, `/comments`, `/approval-log` | Approval |

Kode error yang disarankan: `EXPENSE_LOCKED_PERIOD`, `EXPENSE_RECONCILED`, `EXPENSE_EXTERNAL_SOURCE`, `EXPENSE_HAS_PAYMENT_BELOW_TOTAL`, `EXPENSE_PAYMENT_CONTACT_MISMATCH`, `EXPENSE_NUMBER_DUPLICATE`.

---

## 9. Hak akses

| Izin | Fase | Sumber |
|---|---|---|
| `expense.view`, `expense.create`, `expense.update`, `expense.delete`, `expense.pay` | 1 | CONFIRMED S3 (izin dibuat, ubah, hapus, bayar tagihan lewat peran custom) |
| Batasi lihat hanya biaya buatan sendiri | 1 | CONFIRMED S3 |
| Batasi akun kas/bank dan akun biaya per pengguna atau peran | 3 | CONFIRMED S3 (baca artikel pembatasan CoA dulu) |
| `expense.approve` | 3 | CONFIRMED S4 |

---

## 10. Integrasi (Fase 4)

- **Mekari Expense:** biaya masuk otomatis dengan `source = mekari_expense`, read-only (BR-14, BR-17). Kontrak data belum diketahui: `[OPEN]`, jangan implementasikan sebelum ada spesifikasi.
- **Mekari Pay / Payout:** pembayaran biaya dari Jurnal. Biaya yang dibayar lewat ini tidak boleh dihapus (BR-17). Sediakan interface `PaymentProvider` dengan implementasi stub sampai spesifikasi tersedia.
- **Jurnal Mobile:** hanya untuk approval (Fase 3+), di luar cakupan awal.

---

## 11. Rencana pengujian

Setiap baris di bawah menjadi minimal satu test otomatis. Nama test harus memuat ID BR.

**Perhitungan**
- BR-06: input 100.000, pajak 11% efektif, toggle inklusif, maka DPP 90.090,09 dan pajak 9.909,91 (toleransi sesuai keputusan Q-06)
- BR-05: pemotongan 2% dihitung dari nilai sebelum pajak, bukan setelah
- BR-07: total = subtotal + pajak - pemotongan
- BR-03: 3 baris akun berbeda menghasilkan `Kategori = -Terbagi-` di daftar

**Alur**
- BR-08: bayar langsung menghasilkan status `closed`, sisa 0, jurnal seimbang
- BR-09: bayar nanti menghasilkan status `open`, kredit ke hutang
- BR-12: melunasi 2 biaya dari penerima yang sama berhasil; beda penerima ditolak
- BR-04: nomor kosong menghasilkan 10001, lalu 10002 pada biaya berikutnya

**Pembatasan**
- BR-14: setiap kondisi blokir ubah (rekonsiliasi, kunci periode, sumber Mekari Expense)
- BR-15: ubah nominal di bawah total pelunasan ditolak
- BR-16: ubah penerima/mata uang pada biaya yang sudah dilunasi ditolak
- BR-17 dan BR-18: blokir hapus dan cascade hapus pelunasan
- BR-19: hapus massal dengan campuran item sah dan terblokir

**Kartu ringkasan**
- Kartu 1 dan 2 hanya menghitung biaya **lunas**; kartu 3 hanya **belum lunas** dan lintas periode
- Klik kartu memfilter daftar dengan hasil yang sama dengan angka di kartu

**Multi-tenant:** user company A tidak pernah melihat atau mengubah biaya company B.

---

## 12. OPEN QUESTIONS (harus dijawab manusia)

| ID | Pertanyaan | Blokir |
|---|---|---|
| Q-01 | Sumber saling bertentangan: contoh "harga termasuk pajak" memakai PPN 12% pengali 11/12, tetapi artikel yang sama bilang fitur belum mendukung pengali 11/12. Rumus mana yang dipakai? | Fase 1 (hitung pajak) |
| Q-02 | Apakah pajak bertipe pemotongan tersedia di baris biaya (di luar toggle inklusif)? | Fase 1 |
| Q-03 | Apakah "Bayar dari" disembunyikan atau dinonaktifkan saat "Bayar nanti" dicentang? | Fase 1 (form) |
| Q-04 | Makna dua "Total" di panel kanan bawah (S5) dan efek pemotongan terhadap nilai hutang | Fase 1 (jurnal) |
| Q-05 | Apakah ada jatuh tempo dan status Overdue? | Fase 1 (status) |
| Q-06 | Aturan pembulatan agar jurnal seimbang dengan presisi 6 desimal | Fase 1 |
| Q-07 | Apakah pelunasan sebagian didukung? | Fase 2 |
| Q-08 | Perilaku hapus massal jika sebagian item terblokir | Fase 2 |
| Q-09 | Paginasi, ukuran halaman, dan opsi urut di daftar | Fase 1 |
| Q-10 | Pilihan stack teknologi (jika repositori kosong) | Fase 0 |

Format catatan di `OPEN_QUESTIONS.md`:
```
## Q-XX judul singkat
- Ditemukan di: file/fase
- Yang diketahui:
- Opsi A / Opsi B:
- Rekomendasi agent:
- Status: menunggu / dijawab (tanggal, oleh siapa, keputusan)
```

---

## 13. Checklist agent per sesi kerja

1. Baca bagian dokumen yang relevan dengan fase aktif. Jangan mengandalkan ingatan sesi sebelumnya.
2. Cek `OPEN_QUESTIONS.md`: apakah item yang memblokir tugas ini sudah dijawab?
3. Tulis test dulu untuk BR yang akan disentuh (boleh gagal dulu).
4. Implementasikan. Beri komentar `INFERRED: BR-xx` pada logika yang bersumber dari kesimpulan.
5. Jalankan test, lint, dan typecheck.
6. Perbarui laporan fase. Jangan menyatakan fase selesai sebelum Definition of Done terpenuhi.
7. Jika menemukan konflik antara dokumen ini dan kenyataan di repositori, **berhenti dan laporkan**, jangan diam-diam memilih salah satu.

## 14. Yang dilarang

- Mengarang perilaku Jurnal yang tidak ada di sumber
- Menyalin teks help center Jurnal secara harfiah ke UI atau dokumentasi produk
- Menyimpan atau menghitung uang dengan floating point
- Menghapus permanen transaksi yang sudah membentuk jurnal tanpa membalik jurnalnya
- Melewati pengecekan periode terkunci atau rekonsiliasi, termasuk untuk "kemudahan testing"
- Menyentuh modul dependensi di luar antarmuka publiknya tanpa izin manusia