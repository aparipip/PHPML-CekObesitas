# 🧠 Sistem Klasifikasi Obesitas Menggunakan PHP-ML

Proyek ini merupakan implementasi **metode Naive Bayes** untuk melakukan **klasifikasi obesitas** berdasarkan atribut kesehatan menggunakan library **PHP-ML**. Sistem dikembangkan berbasis web dan dilengkapi dengan fitur evaluasi model serta prediksi manual untuk individu baru.

---

## 📌 Fitur Utama
- Klasifikasi kondisi **Obesitas** dan **Normal**
- Implementasi algoritma **Naive Bayes**
- Evaluasi model menggunakan:
  - Confusion Matrix
  - Accuracy
  - Precision, Recall, dan F1-Score (Classification Report)
- Form **prediksi manual berbasis web**
- Tampilan atribut dan kategori dataset
- Output rapi untuk **CLI dan browser**

---

## 🗂️ Dataset
Dataset terdiri dari **80 data pegawai**, dengan pembagian:
- **Data Training** : Pegawai 1–60
- **Data Testing**  : Pegawai 61–80

### Atribut yang Digunakan
1. Jenis Kelamin  
2. Usia  
3. Tinggi Badan  
4. Berat Badan  
5. Lingkar Perut  
6. Aktivitas Fisik  
7. Pola Konsumsi Gula  
8. Pola Konsumsi Garam  
9. Pola Konsumsi Lemak  
10. Pola Konsumsi Buah dan Sayur  

Label klasifikasi:
- **Obesitas**
- **Normal**

---

## 🛠️ Teknologi yang Digunakan
- **PHP 8.x**
- **PHP-ML (php-ai/php-ml)**
- **Composer**
- **Laragon**
- HTML & CSS

---

## 📁 Struktur Folder
```
deteksi-obesitas/
│
├── dataset.csv
├── train.php
├── predict.php
├── atribut.png
├── composer.json
├── composer.lock
├── vendor/
│   └── autoload.php
└── README.md
```

---

## ⚙️ Instalasi & Konfigurasi

### 1️⃣ Clone Repository
```bash
git clone https://github.com/username/deteksi-obesitas.git
cd deteksi-obesitas
```

### 2️⃣ Install Dependency
```bash
composer install
```

### 3️⃣ Jalankan Evaluasi Model (CLI)
```bash
php train.php
```

### 4️⃣ Jalankan Web Prediction
Letakkan folder proyek ke direktori:
```
C:\laragon\www\
```

Buka browser:
```
http://localhost/deteksi-obesitas/predict.php
```

---

## 📊 Hasil Pengujian Model
- **Akurasi** : 85%
- **Confusion Matrix**:
  - TP = 8
  - FP = 1
  - TN = 9
  - FN = 2

### Classification Report
| Class | Precision | Recall | F1-Score | Support |
|------|-----------|--------|----------|---------|
| Obesitas | 0.89 | 0.80 | 0.84 | 10 |
| Normal | 0.82 | 0.90 | 0.86 | 10 |

---

## 🧪 Prediksi Manual
Sistem menyediakan form input berbasis web untuk memprediksi kondisi obesitas individu baru berdasarkan atribut kesehatan yang diinputkan.

---

## 🎓 Tujuan Akademik
Proyek ini dikembangkan untuk keperluan akademik sebagai implementasi metode machine learning dalam klasifikasi data kesehatan.

---

## 👤 Author
**Nama** : Arif Noer  
**Email** : arifnc6@gmail.com  

---

## 📄 Lisensi
Proyek ini dibuat untuk keperluan akademik dan pembelajaran.
